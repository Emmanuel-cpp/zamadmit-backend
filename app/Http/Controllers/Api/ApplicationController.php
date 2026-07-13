<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Programme;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    /**
     * List all applications for the logged-in student.
     * GET /api/applications
     */
    public function index(Request $request)
    {
        $applications = Application::where('user_id', $request->user()->id)
            ->with(['programme.institution'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($applications);
    }

    /**
     * Submit a new application.
     * POST /api/applications
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Block if profile is not complete
        if (!$user->profile_complete) {
            return response()->json([
                'message' => 'Complete your profile before applying.',
            ], 403);
        }

        $data = $request->validate([
            'programme_id'       => 'required|exists:programmes,id',
            'personal_statement' => 'nullable|string|max:3000',
        ]);

        // Prevent duplicate applications
        $exists = Application::where('user_id', $user->id)
            ->where('programme_id', $data['programme_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'You have already applied to this programme.',
            ], 422);
        }

        $application = Application::create([
            'user_id'            => $user->id,
            'programme_id'       => $data['programme_id'],
            'personal_statement' => $data['personal_statement'] ?? null,
            'status'             => 'submitted',
            'submitted_at'       => now(),
        ]);

        return response()->json(
            $application->load('programme.institution'),
            201
        );
    }

    /**
     * Get a single application.
     * GET /api/applications/{id}
     */
    public function show(Request $request, int $id)
    {
        $application = Application::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with(['programme.institution'])
            ->firstOrFail();

        return response()->json($application);
    }
}