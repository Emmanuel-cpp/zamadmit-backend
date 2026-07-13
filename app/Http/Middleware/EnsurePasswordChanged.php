<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks API access if the authenticated user is flagged with
 * must_change_password. Forces them to change their password
 * before they can do anything else.
 *
 * The only routes allowed through are:
 *   - /api/change-password    (so they can change it)
 *   - /api/logout             (so they can log out)
 *   - /api/user               (so the frontend can read their status)
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            return response()->json([
                'message' => 'You must change your password before continuing.',
                'code'    => 'PASSWORD_CHANGE_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}