<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Public endpoints for accepting an admin invite.
 * The token is single-use: only its SHA-256 hash is stored, and
 * acceptance stamps accepted_at, permanently consuming it.
 */
class InviteController extends Controller
{
    /**
     * GET /api/invites/{token}
     * Validate a token and describe the invite (for the join page).
     */
    public function show(string $token)
    {
        $invite = $this->findValid($token);

        if (!$invite) {
            return response()->json([
                'message' => 'This invite link is invalid or has expired.',
            ], 404);
        }

        return response()->json([
            'email'       => $invite->email,
            'admin_role'  => $invite->admin_role,
            'institution' => $invite->institution->only(['name', 'short_name']),
        ]);
    }

    /**
     * POST /api/invites/{token}/accept
     * Create the admin account and consume the invite.
     */
    public function accept(Request $request, string $token)
    {
        $invite = $this->findValid($token);

        if (!$invite) {
            return response()->json([
                'message' => 'This invite link is invalid or has expired.',
            ], 404);
        }

        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'password'   => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = DB::transaction(function () use ($invite, $data) {
            $user = User::create([
                'first_name'           => $data['first_name'],
                'last_name'            => $data['last_name'],
                'name'                 => $data['first_name'] . ' ' . $data['last_name'],
                'email'                => $invite->email,
                'password'             => Hash::make($data['password']),
                'role'                 => 'institution_admin',
                'admin_role'           => $invite->admin_role,
                'institution_id'       => $invite->institution_id,
                'must_change_password' => false, // they chose their own password
            ]);

            $invite->update(['accepted_at' => now()]);

            return $user;
        });

        $tokenValue = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user'    => $user,
            'token'   => $tokenValue,
            'message' => 'Welcome aboard. Your account is ready.',
        ], 201);
    }

    private function findValid(string $token): ?AdminInvite
    {
        $invite = AdminInvite::where('token_hash', hash('sha256', $token))
            ->with('institution')
            ->first();

        return $invite && $invite->isValid() ? $invite : null;
    }
}