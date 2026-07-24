<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;

/**
 * Public read-only controller for browsing institutions.
 *
 * Suspended institutions are excluded entirely: they disappear from the
 * public site without their data being destroyed, so suspension is fully
 * reversible by a platform administrator.
 */
class InstitutionController extends Controller
{
    /**
     * GET /api/institutions
     */
    public function index(Request $request)
    {
        $institutions = Institution::where('is_suspended', false)
            ->withCount('programmes')
            ->orderBy('name')
            ->get();

        return response()->json($institutions);
    }

    /**
     * GET /api/institutions/{slug}
     */
    public function show(string $slug)
    {
        $institution = Institution::with(['programmes' => function ($q) {
                $q->orderBy('school')->orderBy('name');
            }])
            ->where('slug', $slug)
            ->where('is_suspended', false)
            ->firstOrFail();

        return response()->json($institution);
    }
}