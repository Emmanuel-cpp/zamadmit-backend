<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\AuditLogger;

/**
 * Owner-only management of an institution's admin team.
 *
 * Invite flow: owner creates an invite → receives a one-time link to
 * share with the invitee (in production this would be emailed; in dev
 * the owner shares it manually) → invitee opens the link, sets their
 * name and password → account is created bound to the institution with
 * the assigned role (see InviteController).
 */
class TeamController extends Controller
{
    /**
     * GET /api/admin/team
     * All admins of this institution + pending invites.
     */
    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $admins = User::where('institution_id', $institutionId)
            ->where('role', 'institution_admin')
            ->orderBy('created_at')
            ->get(['id', 'first_name', 'last_name', 'email', 'admin_role', 'created_at']);

        $invites = AdminInvite::where('institution_id', $institutionId)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get(['id', 'email', 'admin_role', 'expires_at', 'created_at']);

        return response()->json([
            'admins'  => $admins,
            'invites' => $invites,
        ]);
    }

    /**
     * POST /api/admin/team/invites
     * Create an invite. Returns the one-time invite URL — shown once,
     * never retrievable again (only its hash is stored).
     */
    public function invite(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $data = $request->validate([
            'email'      => 'required|email|max:255',
            'admin_role' => 'required|in:owner,admissions_officer,viewer',
        ]);

        // Refuse if this email is already an account holder
        if (User::where('email', $data['email'])->exists()) {
            return response()->json([
                'message' => 'A user with this email already exists.',
            ], 422);
        }

        // Replace any previous pending invite for this email
        AdminInvite::where('institution_id', $institutionId)
            ->where('email', $data['email'])
            ->delete();

        $token = Str::random(48);

        $invite = AdminInvite::create([
            'institution_id' => $institutionId,
            'email'          => $data['email'],
            'admin_role'     => $data['admin_role'],
            'token_hash'     => hash('sha256', $token),
            'invited_by'     => $request->user()->id,
            'expires_at'     => now()->addDays(7),
        ]);

        AuditLogger::log('team.invited', $invite,
            new: ['email' => $data['email'], 'admin_role' => $data['admin_role']]);
        $frontend = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');

        return response()->json([
            'invite'     => $invite->only(['id', 'email', 'admin_role', 'expires_at']),
            'invite_url' => "{$frontend}/join?token={$token}",
            'message'    => 'Share this link with the invitee. It can be used once and expires in 7 days.',
        ], 201);
    }

    /**
     * DELETE /api/admin/team/invites/{id}
     * Revoke a pending invite.
     */
    public function revokeInvite(Request $request, int $id)
    {
        AdminInvite::where('id', $id)
            ->where('institution_id', $request->user()->institution_id)
            ->whereNull('accepted_at')
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Invite revoked.']);
    }

    /**
     * PUT /api/admin/team/{userId}
     * Change a team member's role.
     */
    public function updateRole(Request $request, int $userId)
    {
        $data = $request->validate([
            'admin_role' => 'required|in:owner,admissions_officer,viewer',
        ]);

        $member = User::where('id', $userId)
            ->where('institution_id', $request->user()->institution_id)
            ->where('role', 'institution_admin')
            ->firstOrFail();

        // Safety: an institution must always retain at least one owner.
        if (
            $member->admin_role === 'owner'
            && $data['admin_role'] !== 'owner'
            && $this->ownerCount($request->user()->institution_id) <= 1
        ) {
            return response()->json([
                'message' => 'You cannot demote the last owner. Promote another owner first.',
            ], 422);
        }

        $previousRole = $member->admin_role;

        $member->update(['admin_role' => $data['admin_role']]);

        AuditLogger::log('team.role_changed', $member,
            old: ['admin_role' => $previousRole],
            new: ['admin_role' => $data['admin_role']]);

        return response()->json($member->only(['id', 'first_name', 'last_name', 'email', 'admin_role']));
    }

    /**
     * DELETE /api/admin/team/{userId}
     * Remove an admin from the institution.
     */
    public function remove(Request $request, int $userId)
    {
        // You cannot remove yourself — prevents accidental lockout.
        if ($userId === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot remove your own account.',
            ], 422);
        }

        $member = User::where('id', $userId)
            ->where('institution_id', $request->user()->institution_id)
            ->where('role', 'institution_admin')
            ->firstOrFail();

        if (
            $member->admin_role === 'owner'
            && $this->ownerCount($request->user()->institution_id) <= 1
        ) {
            return response()->json([
                'message' => 'You cannot remove the last owner.',
            ], 422);
        }

        AuditLogger::log('team.removed', $member,
                    old: ['email' => $member->email, 'admin_role' => $member->admin_role]);

                // Revoke their tokens, then delete the account.
                $member->tokens()->delete();
                $member->delete();

        return response()->json(['message' => 'Admin removed.']);
    }

    private function ownerCount(int $institutionId): int
    {
        return User::where('institution_id', $institutionId)
            ->where('role', 'institution_admin')
            ->where('admin_role', 'owner')
            ->count();
    }
}