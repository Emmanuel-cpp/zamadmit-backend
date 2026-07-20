<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Programme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\AuditLogger;

/**
 * Admin-only CRUD for programmes within the authenticated admin's institution.
 */
class ProgrammeController extends Controller
{
    /**
     * GET /api/admin/programmes
     */
    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $programmes = Programme::where('institution_id', $institutionId)
            ->with('requirements')
            ->withCount('applications')
            ->orderBy('school')
            ->orderBy('name')
            ->get();

        return response()->json($programmes);
    }

    /**
     * GET /api/admin/programmes/{id}
     */
    public function show(Request $request, int $id)
    {
        $programme = Programme::where('institution_id', $request->user()->institution_id)
            ->with('requirements')
            ->withCount('applications')
            ->findOrFail($id);

        return response()->json($programme);
    }

    /**
     * POST /api/admin/programmes
     */
    public function store(Request $request)
    {
        $institutionId = $request->user()->institution_id;
        $data = $this->validateRequest($request, $institutionId);

        $programme = DB::transaction(function () use ($data, $institutionId) {
            $programme = Programme::create([
                'slug'           => $this->generateUniqueSlug($data['name'], $institutionId),
                'institution_id' => $institutionId,
                'name'           => $data['name'],
                'qualification'  => $data['qualification'],
                'school'         => $data['school'],
                'duration_years' => $data['duration_years'],
                'study_mode'     => $data['study_mode'],
                'intake'         => $data['intake'],
                'description'    => $data['description'] ?? null,
                'capacity' => $data['capacity'] ?? null,
            ]);

            foreach ($data['requirements'] ?? [] as $req) {
                $programme->requirements()->create([
                    'subject'   => $req['subject'],
                    'min_grade' => $req['min_grade'],
                ]);
            }

                    return $programme;
                });

                AuditLogger::log('programme.created', $programme,
                    new: ['name' => $programme->name, 'school' => $programme->school]);

                return response()->json(
                    $programme->fresh()->load('requirements'),
                    201,
                );
    }

    /**
     * PUT /api/admin/programmes/{id}
     */
    public function update(Request $request, int $id)
    {
        $institutionId = $request->user()->institution_id;
        $programme = Programme::where('institution_id', $institutionId)
            ->findOrFail($id);
        $data = $this->validateRequest($request, $institutionId, $programme->id);
        $oldSnapshot = $programme->only(['name', 'school', 'capacity', 'intake']);
        DB::transaction(function () use ($programme, $data) {
            $programme->update([
                'name'           => $data['name'],
                'qualification'  => $data['qualification'],
                'school'         => $data['school'],
                'duration_years' => $data['duration_years'],
                'study_mode'     => $data['study_mode'],
                'intake'         => $data['intake'],
                'description'    => $data['description'] ?? null,
                'capacity' => $data['capacity'] ?? null,
            ]);

            $programme->requirements()->delete();
            foreach ($data['requirements'] ?? [] as $req) {
                $programme->requirements()->create([
                    'subject'   => $req['subject'],
                    'min_grade' => $req['min_grade'],
                ]);
            }
});

        AuditLogger::log('programme.updated', $programme,
            old: $oldSnapshot,
            new: $programme->fresh()->only(['name', 'school', 'capacity', 'intake']));

        return response()->json($programme->fresh()->load('requirements'));
    }

    /**
     * Validation rules shared by store + update.
     */
    private function validateRequest(Request $request, int $institutionId, ?int $programmeId = null): array
    {
        return $request->validate([
            'name'              => 'required|string|max:255',
            'qualification'     => 'required|string|in:Certificate,Diploma,Bachelor,Honours,Masters,PhD',
            'school'            => 'required|string|max:255',
            'duration_years'    => 'required|integer|min:1|max:8',
            'study_mode'        => 'required|string|in:Full-time,Part-time,Distance',
            'intake'            => 'required|string|max:100',
            'description'       => 'nullable|string|max:5000',

            'requirements'             => 'nullable|array|max:12',
            'requirements.*.subject'   => 'required|string|max:100',
            'requirements.*.min_grade' => 'required|integer|min:1|max:9',
            'capacity' => 'nullable|integer|min:1|max:100000',
        ]);
    }

    /**
     * Generate a URL-safe unique slug from the programme name.
     */
    private function generateUniqueSlug(string $name, int $institutionId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 2;

        while (Programme::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}