<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;

/**
 * Public read-only controller for browsing institutions.
 *
 * Anyone (including unauthenticated visitors) can list all institutions
 * and view any single institution's details. This powers the public
 * /institutions browse page and the institution detail page.
 */
class InstitutionController extends Controller
{
    /**
     * GET /api/institutions
     *
     * List all institutions for public browsing.
     */
    public function index(Request $request)
    {
        $institutions = Institution::withCount('programmes')
            ->orderBy('name')
            ->get();

        return response()->json($institutions);
    }

    /**
     * GET /api/institutions/{slug}
     *
     * Single institution detail, including its nested programmes.
     */
    public function show(string $slug)
    {
        $institution = Institution::with(['programmes' => function ($q) {
                $q->orderBy('school')->orderBy('name');
            }])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json($institution);
    }
}