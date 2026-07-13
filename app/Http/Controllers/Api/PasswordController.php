<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Change the authenticated user's password.
     * POST /api/change-password
     *
     * Used by all roles, but especially required for institution admins
     * on first login (must_change_password = true).
     */
    public function change(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password'     => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers(),
            ],
        ]);

        // Verify the current password
        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'errors'  => ['current_password' => ['Current password is incorrect.']],
            ], 422);
        }

        // Prevent reusing the same password
        if (Hash::check($data['new_password'], $user->password)) {
            return response()->json([
                'message' => 'New password must be different from your current password.',
                'errors'  => ['new_password' => ['Please choose a different password.']],
            ], 422);
        }

        // Update password, clear the flag, set the audit timestamp
        $user->update([
            'password'             => Hash::make($data['new_password']),
            'must_change_password' => false,
            'password_changed_at'  => now(),
        ]);

        // Invalidate all other tokens for security — they keep only the current one
        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json([
            'message' => 'Password changed successfully.',
            'user'    => [
                'id'                   => $user->id,
                'must_change_password' => false,
                'password_changed_at'  => $user->password_changed_at?->toIso8601String(),
            ],
        ]);
    }
}