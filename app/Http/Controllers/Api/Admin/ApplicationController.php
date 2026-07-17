<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Exports\ApplicationsBySchoolExport;
use App\Models\Application;
use App\Models\Institution;
use App\Services\MatchScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Admin-side application review: list, inspect, decide, export.
 * Every query is tenancy-scoped to the admin's own institution.
 */
class ApplicationController extends Controller
{
    /**
     * GET /api/admin/applications
     * List all applications to this institution's programmes,
     * each annotated with its requirements-match score.
     */
    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $applications = Application::whereHas('programme', function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            })
            ->where('status', '!=', 'draft')
            ->with(['user.grades', 'programme.requirements', 'programme.institution'])
            ->orderByDesc('submitted_at')
            ->get();

        $scorer = new MatchScoreService();
        $applications->each(fn ($app) => $app->match_score = $scorer->score($app));

        return response()->json($applications);
    }

    /**
     * GET /api/admin/applications/{id}
     */
    public function show(Request $request, int $id)
    {
        $institutionId = $request->user()->institution_id;

        $application = Application::whereHas('programme', function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            })
            ->with(['user.grades', 'user.documents', 'programme.requirements', 'programme.institution'])
            ->findOrFail($id);

        $application->match_score = (new MatchScoreService())->score($application);

        return response()->json($application);
    }

    /**
     * PUT /api/admin/applications/{id}
     * Update decision and internal note. On first acceptance, assigns
     * the institution's next student number. On any status change,
     * notifies the applicant.
     */
    public function update(Request $request, int $id)
    {
        $institutionId = $request->user()->institution_id;

        $application = Application::whereHas('programme', function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            })
            ->with(['programme.institution', 'user'])
            ->findOrFail($id);

        $data = $request->validate([
            'status'        => 'sometimes|in:under_review,accepted,rejected,waitlisted',
            'internal_note' => 'sometimes|nullable|string|max:1000',
        ]);

        $previousStatus = $application->status;

        if (isset($data['status']) && in_array($data['status'], ['accepted', 'rejected', 'waitlisted'])) {
            $data['decision_at'] = now();
        }

        // Assign a student number on FIRST acceptance only. Generated in a
        // transaction with a row lock on the institution counter, so two
        // admins accepting simultaneously can never produce a collision.
        if (
            isset($data['status'])
            && $data['status'] === 'accepted'
            && $application->student_number === null
        ) {
            $data['student_number'] = $this->generateStudentNumber($institutionId);
        }

        $application->update($data);

        if (isset($data['status']) && $data['status'] !== $previousStatus) {
            $this->notifyApplicant(
                $application->fresh(['programme.institution', 'user']),
                $data['status'],
            );
        }

        return response()->json($application->fresh()->load('programme', 'user'));
    }

    /**
     * GET /api/admin/applications/export
     * Streams an .xlsx workbook (Summary + one sheet per school),
     * honouring the same filters as the list view.
     */
    public function export(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $query = Application::whereHas('programme', function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            })
            ->with(['user', 'programme']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('programme_id') && $request->programme_id !== 'all') {
            $query->where('programme_id', $request->programme_id);
        }

        if ($request->filled('search')) {
            $term = strtolower($request->search);
            $query->where(function ($q) use ($term) {
                $q->whereHas('user', function ($uq) use ($term) {
                    $uq->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                       ->orWhereRaw('LOWER(last_name) LIKE ?',  ["%{$term}%"])
                       ->orWhereRaw('LOWER(email) LIKE ?',      ["%{$term}%"])
                       ->orWhereRaw('LOWER(nrc) LIKE ?',        ["%{$term}%"]);
                })->orWhereHas('programme', function ($pq) use ($term) {
                    $pq->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]);
                });
            });
        }

        $applications = $query->orderByDesc('submitted_at')->get();

        $institutionName = Institution::find($institutionId)?->name ?? 'Institution';

        $filename = 'applications-by-school-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new ApplicationsBySchoolExport($applications, $institutionName),
            $filename,
        );
    }

/**
 * Generate the next student number: PREFIX + zero-padded sequence,
 * padded to the institution's configured total length.
 *
 * Example: prefix "2610", length 8 → 26100001, 26100002, … 26109999.
 * When the sequence space is exhausted, the prefix auto-increments
 * (2610 → 2611) and the sequence resets to 1.
 *
 * Runs in a transaction with a row lock on the institution, so two
 * admins accepting simultaneously can never produce a collision.
 */
private function generateStudentNumber(int $institutionId): string
{
    return DB::transaction(function () use ($institutionId) {
        $institution = Institution::where('id', $institutionId)
            ->lockForUpdate()
            ->first();

        // Defaults: current 2-digit year as prefix, 8 total digits (CBU style)
        $prefix = $institution->student_number_prefix ?: now()->format('y');
        $length = max(
            $institution->student_number_length ?: 8,
            strlen($prefix) + 1, // always leave at least 1 sequence digit
        );

        $seqDigits = $length - strlen($prefix);
        $maxSeq    = (10 ** $seqDigits) - 1;
        $seq       = $institution->next_student_seq;

        // Sequence space exhausted → roll the prefix forward, reset sequence
        if ($seq > $maxSeq) {
            $prefix = (string) (((int) $prefix) + 1);
            $seq    = 1;
            $institution->student_number_prefix = $prefix;
            // Recompute in case the incremented prefix grew a digit
            $seqDigits = max(1, $length - strlen($prefix));
        }

        $number = $prefix . str_pad((string) $seq, $seqDigits, '0', STR_PAD_LEFT);

        $institution->next_student_seq = $seq + 1;
        $institution->save();

        return $number;
    });
}

    /**
     * Create a notification for the applicant whose status changed.
     */
    private function notifyApplicant(Application $application, string $newStatus): void
    {
        $programmeName   = $application->programme?->name ?? 'your programme';
        $institutionName = $application->programme?->institution?->name ?? 'the institution';

        [$title, $body] = match ($newStatus) {
            'accepted' => [
                'Congratulations — you have been accepted!',
                "{$institutionName} has accepted your application for {$programmeName}."
                    . ($application->student_number ? " Your student number is {$application->student_number}." : '')
                    . ' Tap to see the details.',
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