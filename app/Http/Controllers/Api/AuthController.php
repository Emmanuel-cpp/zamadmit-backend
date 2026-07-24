<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Services\AuditLogger;

class AuthController extends Controller
{
    /**
     * Register a new student account.
     * POST /api/register
     */
   public function register(Request $request)
        {
            $data = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name'  => 'required|string|max:255',
                'email'      => 'required|string|email|unique:users',
                'password'   => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'name'       => $data['first_name'] . ' ' . $data['last_name'],
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
                'role'       => 'student',
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user'  => $this->formatUser($user),
                'token' => $token,
            ], 201);
        }

    /**
     * Login and return a token.
     * POST /api/login
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            AuditLogger::log('auth.login_failed', null,
                new: ['email' => $data['email']]);
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Delete old tokens so only one active session at a time
        $user->tokens()->delete();

        // Suspended accounts cannot sign in.
        if ($user->is_suspended) {
            AuditLogger::log('auth.login_blocked', $user,
                new: ['reason' => 'account_suspended'], userId: $user->id);

            return response()->json([
                'message' => 'This account has been suspended. Contact ZamAdmit support.',
            ], 403);
        }

        // Institution admins cannot sign in while their institution is suspended.
        if ($user->role === 'institution_admin' && $user->institution?->is_suspended) {
            AuditLogger::log('auth.login_blocked', $user,
                new: ['reason' => 'institution_suspended'], userId: $user->id);

            return response()->json([
                'message' => 'Your institution\'s account is currently suspended. Contact ZamAdmit support.',
            ], 403);
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        AuditLogger::log('auth.login', $user, userId: $user->id,
            institutionId: $user->institution_id);

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    /**
     * Logout — invalidate the current token.
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Get the currently authenticated user.
     * GET /api/user
     */
    public function user(Request $request)
    {
        return response()->json($this->formatUser($request->user()));
    }


   private function formatUser(User $user): array
        {
            return [
                'id'                   => $user->id,
                'first_name'           => $user->first_name,
                'last_name'            => $user->last_name,
                'full_name'            => $user->full_name,
                'email'                => $user->email,
                'nrc'                  => $user->nrc,
                'phone'                => $user->phone,
                'province'             => $user->province,
                'interests'            => $user->interests ?? [],
                'date_of_birth'        => $user->date_of_birth?->format('Y-m-d'),
                'role'                 => $user->role,
                'profile_complete'     => $user->profile_complete,
                'must_change_password' => $user->must_change_password,
                'institution_id'       => $user->institution_id,
                'admin_role'           => $user->admin_role,
            ];
        }
}