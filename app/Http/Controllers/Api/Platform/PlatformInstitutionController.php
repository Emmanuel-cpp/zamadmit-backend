<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Platform-level institution management.
 *
 * Onboarding an institution and provisioning its first owner happen in one
 * atomic transaction: an institution must never exist without someone able
 * to administer it, and an orphaned owner account must never exist without
 * an institution.
 */
class PlatformInstitutionController extends Controller
{
    /**
     * GET /api/platform/institutions
     * Every institution, suspended or not, with operational counts.
     */
    public function index()
    {
        $institutions = Institution::withCount([
                'programmes',
                'users as admins_count' => fn ($q) => $q->where('role', 'institution_admin'),
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($i) {
                $i->applications_count = DB::table('applications')
                    ->join('programmes', 'applications.programme_id', '=', 'programmes.id')
                    ->where('programmes.institution_id', $i->id)
                    ->where('applications.status', '!=', 'draft')
                    ->count();
                return $i;
            });

        return response()->json($institutions);
    }

    /**
     * GET /api/platform/institutions/{id}
     */
    public function show(int $id)
    {
        $institution = Institution::withCount('programmes')
            ->with(['users' => fn ($q) => $q
                ->where('role', 'institution_admin')
                ->select('id', 'institution_id', 'first_name', 'last_name', 'email', 'admin_role', 'is_suspended', 'created_at')])
            ->findOrFail($id);

        return response()->json($institution);
    }

    /**
     * POST /api/platform/institutions
     *
     * Creates the institution AND its first owner account in one
     * transaction. Returns the owner's temporary password once —
     * it is never retrievable again.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'short_name' => 'required|string|max:50',
            'city'       => 'required|string|max:100',
            'province'   => 'required|string|max:100',
            'type'       => 'nullable|string|max:50',
            'description'            => 'nullable|string|max:5000',
            'application_fee'        => 'required|numeric|min:20|max:10000',
            'application_deadline'   => 'required|date',
            'student_number_prefix'  => 'nullable|string|max:20|regex:/^\d*$/',
            'student_number_length'  => 'nullable|integer|min:4|max:20',

            // First owner
            'owner_first_name' => 'required|string|max:255',
            'owner_last_name'  => 'required|string|max:255',
            'owner_email'      => 'required|email|max:255|unique:users,email',
        ]);

        $tempPassword = Str::password(14);

        $result = DB::transaction(function () use ($data, $request, $tempPassword) {
            $institution = Institution::create([
                'slug'       => $this->uniqueSlug($data['name']),
                'name'       => $data['name'],
                'short_name' => $data['short_name'],
                'city'       => $data['city'],
                'province'   => $data['province'],
                'type'       => $data['type'] ?? 'University',
                'description'               => $data['description'] ?? null,
                'application_fee'           => $data['application_fee'],
                'application_deadline'      => $data['application_deadline'],
                'student_number_prefix'     => $data['student_number_prefix'] ?? null,
                'student_number_length'     => $data['student_number_length'] ?? 8,
                'is_accepting_applications' => true,
                'is_suspended'              => false,
                'onboarded_by'              => $request->user()->id,
            ]);

            $owner = User::create([
                'first_name'           => $data['owner_first_name'],
                'last_name'            => $data['owner_last_name'],
                'name'                 => $data['owner_first_name'] . ' ' . $data['owner_last_name'],
                'email'                => $data['owner_email'],
                'password'             => Hash::make($tempPassword),
                'role'                 => 'institution_admin',
                'admin_role'           => 'owner',
                'institution_id'       => $institution->id,
                'must_change_password' => true,
                'profile_complete'     => true,
            ]);

            return compact('institution', 'owner');
        });

        AuditLogger::log('platform.institution_created', $result['institution'],
            new: [
                'name'        => $result['institution']->name,
                'owner_email' => $result['owner']->email,
            ]);

        return response()->json([
            'institution' => $result['institution'],
            'owner' => [
                'name'  => $result['owner']->name,
                'email' => $result['owner']->email,
            ],
            'temporary_password' => $tempPassword,
            'message' => 'Institution onboarded. Share these credentials securely — the password is shown once and must be changed at first sign-in.',
        ], 201);
    }

    /**
     * PUT /api/platform/institutions/{id}/suspend
     * Reversible. Hides the institution publicly and blocks its admins.
     */
    public function suspend(Request $request, int $id)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $institution = Institution::findOrFail($id);

        $institution->update([
            'is_suspended'      => true,
            'suspended_at'      => now(),
            'suspension_reason' => $data['reason'],
        ]);

        AuditLogger::log('platform.institution_suspended', $institution,
            new: ['name' => $institution->name, 'reason' => $data['reason']]);

        return response()->json([
            'institution' => $institution->fresh(),
            'message'     => 'Institution suspended. Its data is retained and the action is reversible.',
        ]);
    }

    /**
     * PUT /api/platform/institutions/{id}/reactivate
     */
    public function reactivate(int $id)
    {
        $institution = Institution::findOrFail($id);

        $institution->update([
            'is_suspended'      => false,
            'suspended_at'      => null,
            'suspension_reason' => null,
        ]);

        AuditLogger::log('platform.institution_reactivated', $institution,
            new: ['name' => $institution->name]);

        return response()->json([
            'institution' => $institution->fresh(),
            'message'     => 'Institution reactivated.',
        ]);
    }

    /**
     * PUT /api/platform/users/{id}/suspend
     * Suspend an individual institution admin account.
     */
    public function suspendUser(Request $request, int $id)
    {
        $user = User::where('id', $id)
            ->where('role', 'institution_admin')
            ->firstOrFail();

        $user->update(['is_suspended' => true, 'suspended_at' => now()]);
        $user->tokens()->delete(); // end active sessions immediately

        AuditLogger::log('platform.admin_suspended', $user,
            new: ['email' => $user->email], institutionId: $user->institution_id);

        return response()->json(['message' => 'Administrator account suspended.']);
    }

    /**
     * PUT /api/platform/users/{id}/reactivate
     */
    public function reactivateUser(int $id)
    {
        $user = User::where('id', $id)
            ->where('role', 'institution_admin')
            ->firstOrFail();

        $user->update(['is_suspended' => false, 'suspended_at' => null]);

        AuditLogger::log('platform.admin_reactivated', $user,
            new: ['email' => $user->email], institutionId: $user->institution_id);

        return response()->json(['message' => 'Administrator account reactivated.']);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $n = 2;

        while (Institution::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}