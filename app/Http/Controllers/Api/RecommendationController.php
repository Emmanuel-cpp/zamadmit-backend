<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RecommendationController extends Controller
{
    public function __construct(
        private RecommendationService $service,
    ) {}

    /**
     * GET /api/recommendations
     *
     * Generates AI-powered programme recommendations for the logged-in
     * student based on their grades, province, and the current catalog
     * of open programmes.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Only students get recommendations
        if ($user->role !== 'student') {
            return response()->json([
                'message' => 'Recommendations are only available to student accounts.',
            ], 403);
        }

        // Profile must be complete (otherwise there's no useful signal)
        if (!$user->profile_complete) {
            return response()->json([
                'message' => 'Complete your profile to get personalized recommendations.',
                'code'    => 'PROFILE_INCOMPLETE',
            ], 422);
        }

        try {
            $result = $this->service->recommend($user);
            return response()->json($result);

        } catch (\Throwable $e) {
            Log::error('Recommendation generation failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not generate recommendations right now. Please try again in a moment.',
            ], 500);
        }
    }
}