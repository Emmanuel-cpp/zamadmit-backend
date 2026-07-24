<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to ZamAdmit platform administrators.
 *
 * Platform admins sit above institution admins: they provision and suspend
 * institutions and see platform-wide aggregates. They deliberately hold NO
 * admissions authority — no route guarded by this middleware exposes
 * applicant personal data or decision powers. Provisioning authority and
 * admissions authority are separate by design.
 */
class PlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'platform_admin') {
            return response()->json([
                'message' => 'This area is restricted to ZamAdmit platform administrators.',
            ], 403);
        }

        return $next($request);
    }
}