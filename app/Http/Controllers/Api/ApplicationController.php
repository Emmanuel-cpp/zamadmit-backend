<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Payment;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    /**
     * List all applications for the logged-in student.
     * GET /api/applications
     *
     * Draft (unpaid) applications are included so the frontend can offer
     * "complete your payment", each with its latest payment attached.
     */
    public function index(Request $request)
    {
        $applications = Application::where('user_id', $request->user()->id)
            ->with(['programme.institution', 'payments' => fn ($q) => $q->latest()])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($applications);
    }

    /**
     * Create a new application as a DRAFT.
     * POST /api/applications
     *
     * The application is not visible to the institution until the
     * application fee is paid — payment completion (PaymentController::confirm)
     * is what moves it to 'submitted'. The response includes the fee and
     * split so the frontend can present the payment step immediately.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->profile_complete) {
            return response()->json([
                'message' => 'Complete your profile before applying.',
            ], 403);
        }

        $data = $request->validate([
            'programme_id'       => 'required|exists:programmes,id',
            'personal_statement' => 'nullable|string|max:3000',
        ]);

        // Prevent duplicates — but allow retrying payment on an existing draft.
        $existing = Application::where('user_id', $user->id)
            ->where('programme_id', $data['programme_id'])
            ->first();

        if ($existing && $existing->status !== 'draft') {
            return response()->json([
                'message' => 'You have already applied to this programme.',
            ], 422);
        }

        if ($existing && $existing->status === 'draft') {
            // Resume the unpaid draft rather than erroring or duplicating.
            $existing->update([
                'personal_statement' => $data['personal_statement'] ?? $existing->personal_statement,
            ]);
            $application = $existing;
        } else {
            $application = Application::create([
                'user_id'            => $user->id,
                'programme_id'       => $data['programme_id'],
                'personal_statement' => $data['personal_statement'] ?? null,
                'status'             => 'draft',
                'submitted_at'       => null,
            ]);
        }

        // Capacity gate (soft) — refuse new drafts for full programmes.
        // The authoritative check happens transactionally at payment time.
        $programme = \App\Models\Programme::findOrFail($data['programme_id']);
        if ($programme->isFull()) {
            return response()->json([
                'message' => 'This programme has reached its capacity and is no longer accepting applications.',
            ], 422);
        }

        $application->load('programme.institution');

        $fee         = (float) ($application->programme->institution->application_fee ?? 150.00);
        $platformFee = round($fee * Payment::PLATFORM_RATE, 2);

        return response()->json([
            'application' => $application,
            'payment_due' => [
                'amount'             => number_format($fee, 2, '.', ''),
                'platform_fee'       => number_format($platformFee, 2, '.', ''),
                'institution_amount' => number_format($fee - $platformFee, 2, '.', ''),
                'currency'           => 'ZMW',
            ],
        ], 201);
    }

    /**
     * Get a single application.
     * GET /api/applications/{id}
     */
    public function show(Request $request, int $id)
    {
        $application = Application::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with(['programme.institution', 'payments' => fn ($q) => $q->latest()])
            ->firstOrFail();

        return response()->json($application);
    }
}