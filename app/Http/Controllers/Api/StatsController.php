<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Document;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\User;

/**
 * Public platform statistics for the landing page.
 *
 * Every figure is computed live from the database — no hardcoded
 * marketing numbers. Unauthenticated: these are aggregate counts
 * only, with no personal data.
 */
class StatsController extends Controller
{
    /**
     * GET /api/stats
     */
    public function index()
    {
        return response()->json([
            // Registered applicants
            'students' => User::where('role', 'student')->count(),

            // Applications that actually reached an institution
            // (drafts are unpaid and invisible to institutions)
            'applications' => Application::where('status', '!=', 'draft')->count(),

            // Institutions on the platform, and how many are open right now
            'institutions'      => Institution::count(),
            'institutions_open' => Institution::where('is_accepting_applications', true)->count(),

            // Catalogue size
            'programmes' => Programme::count(),

            // Geographic reach — distinct provinces with an institution
            'provinces' => Institution::distinct()->count('province'),

            // AI verification volume
            'documents_verified' => Document::where('verification_status', 'verified')->count(),
        ]);
    }
}