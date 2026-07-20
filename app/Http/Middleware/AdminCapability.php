<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Capability-based authorization for institution admins.
 *
 * Usage in routes:  ->middleware('admin.can:decide')
 *
 * The capability → role map is the single source of truth for what each
 * admin tier may do. Roles are hierarchical in effect but declared
 * explicitly here so the policy is readable at a glance:
 *
 *   view              — everyone (owner, admissions_officer, viewer)
 *   decide            — owner, admissions_officer
 *   manage_programmes — owner
 *   manage_settings   — owner
 *   manage_admins     — owner
 */
class AdminCapability
{
    private const CAPABILITIES = [
        'view'              => ['owner', 'admissions_officer', 'viewer'],
        'decide'            => ['owner', 'admissions_officer'],
        'manage_programmes' => ['owner'],
        'manage_settings'   => ['owner'],
        'manage_admins'     => ['owner'],
    ];

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $user = $request->user();

        $allowedRoles = self::CAPABILITIES[$capability] ?? [];

        if (!$user || !in_array($user->admin_role, $allowedRoles, true)) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}