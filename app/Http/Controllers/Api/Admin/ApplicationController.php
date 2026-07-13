<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    /**
     * List all applications for the admin's institution.
     * GET /api/admin/applications
     */
    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $applications = Application::whereHas('programme', function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            })
            ->with([
                'user',
                'user.documents',
                'programme',
            ])
            ->orderByDesc('submitted_at')
            ->get();

        return response()->json($applications);
    }

    /**
     * Get a single applicant's full detail.
     * GET /api/admin/applications/{id}
     */
    public function show(Request $request, int $id)
    {
        $institutionId = $request->user()->institution_id;

        $application = Application::whereHas('programme', function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            })
            ->with([
                'user',
                'user.documents',
                'programme.requirements',
            ])
            ->findOrFail($id);

        return response()->json($application);
    }

    /**
     * Update decision and internal note.
     * PUT /api/admin/applications/{id}
     */
/**
 * Update decision and internal note.
 * PUT /api/admin/applications/{id}
 *
 * When the status changes to a terminal/visible state, this also creates
 * a notification for the applicant so they don't have to keep checking
 * back manually.
 */
public function update(Request $request, int $id)
{
    $institutionId = $request->user()->institution_id;

    $application = Application::whereHas('programme', function ($q) use ($institutionId) {
            $q->where('institution_id', $institutionId);
        })
        ->with(['programme', 'user'])
        ->findOrFail($id);

    $data = $request->validate([
        'status'        => 'sometimes|in:under_review,accepted,rejected,waitlisted',
        'internal_note' => 'sometimes|nullable|string|max:1000',
    ]);

    $previousStatus = $application->status;

    // Set decision timestamp when a final decision is made
    if (isset($data['status']) && in_array($data['status'], ['accepted', 'rejected', 'waitlisted'])) {
        $data['decision_at'] = now();
    }

    $application->update($data);

    // If status changed, notify the applicant.
    // We only fire on actual changes — not on internal-note-only updates.
    if (isset($data['status']) && $data['status'] !== $previousStatus) {
        $this->notifyApplicant($application, $data['status']);
    }

    return response()->json($application->fresh()->load('programme', 'user'));
}

/**
 * Create a notification record for the applicant whose status changed.
 * The wording is intentionally simple and warm — these are read on phones.
 */
private function notifyApplicant(\App\Models\Application $application, string $newStatus): void
{
    $programmeName   = $application->programme?->name ?? 'your programme';
    $institutionName = $application->programme?->institution?->name ?? 'the institution';

    [$title, $body] = match ($newStatus) {
        'accepted' => [
            'Congratulations — you have been accepted!',
            "{$institutionName} has accepted your application for {$programmeName}. Tap to see the next steps.",
        ],
        'rejected' => [
            'Application decision update',
            "We have a decision on your application to {$programmeName} at {$institutionName}. Tap to view the details.",
        ],
        'waitlisted' => [
            "You're on the waitlist",
            "{$institutionName} has placed your application for {$programmeName} on the waitlist. Tap to learn more.",
        ],
        'under_review' => [
            'Your application is being reviewed',
            "{$institutionName} has started reviewing your application for {$programmeName}.",
        ],
        default => [
            'Application status updated',
            "There is an update on your application to {$programmeName}.",
        ],
    };

    \App\Models\Notification::create([
        'user_id' => $application->user_id,
        'type'    => "application_{$newStatus}",
        'title'   => $title,
        'body'    => $body,
        'link'    => "/applications/{$application->id}",
    ]);
    }
}