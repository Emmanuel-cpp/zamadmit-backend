<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Programme;

/**
 * Public read-only controller for browsing programmes.
 *
 * Programmes belonging to suspended institutions are excluded, mirroring
 * the institution listing. Each programme carries an `is_full` flag so the
 * public UI can show capacity state before a student begins applying.
 */
class ProgrammeController extends Controller
{
    /**
     * GET /api/programmes
     */
    public function index()
    {
        $programmes = Programme::whereHas('institution', fn ($q) => $q->where('is_suspended', false))
            ->with('institution')
            ->with('requirements')
            ->orderBy('name')
            ->get();

        $programmes->each(fn ($p) => $p->is_full = $p->isFull());

        return response()->json($programmes);
    }

    /**
     * GET /api/programmes/{slug}
     */
    public function show(string $slug)
    {
        $programme = Programme::whereHas('institution', fn ($q) => $q->where('is_suspended', false))
            ->with('institution')
            ->with('requirements')
            ->where('slug', $slug)
            ->firstOrFail();

        $programme->is_full = $programme->isFull();

        return response()->json($programme);
    }
}