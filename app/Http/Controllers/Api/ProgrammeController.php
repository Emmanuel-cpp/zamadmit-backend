<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Programme;

/**
 * Public read-only controller for browsing programmes.
 *
 * Anyone (including unauthenticated visitors) can list all programmes
 * and view any single programme's details. This powers the public
 * /programmes browse page and the programme detail page.
 */
class ProgrammeController extends Controller
{
    /**
     * GET /api/programmes
     *
     * List all programmes with their institution data nested in.
     */
    public function index()
    {
        $programmes = Programme::with('institution')
            ->with('requirements')
            ->orderBy('name')
            ->get();

        return response()->json($programmes);
    }

    /**
     * GET /api/programmes/{slug}
     *
     * Single programme detail, including institution and requirements.
     */
    public function show(string $slug)
    {
        $programme = Programme::with('institution')
            ->with('requirements')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json($programme);
    }
}