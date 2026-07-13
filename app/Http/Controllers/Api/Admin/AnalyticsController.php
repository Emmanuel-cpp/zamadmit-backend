<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Provides pre-aggregated analytics for the institution admin dashboard.
 *
 * All metrics are scoped to the authenticated admin's institution — admins
 * cannot see data for institutions other than their own. This is enforced
 * via the `whereHas('programme', ...)` filter applied to every query.
 */
class AnalyticsController extends Controller
{
    /**
     * GET /api/admin/analytics
     *
     * Returns:
     *   - daily_submissions: applications per day for the last 30 days
     *   - status_distribution: counts grouped by current status
     *   - programme_popularity: top programmes by application count
     *   - province_distribution: applicants grouped by their home province
     */
    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        return response()->json([
            'daily_submissions'     => $this->dailySubmissions($institutionId),
            'status_distribution'   => $this->statusDistribution($institutionId),
            'programme_popularity'  => $this->programmePopularity($institutionId),
            'province_distribution' => $this->provinceDistribution($institutionId),
        ]);
    }

    /**
     * Submissions per day for the past 30 days, including zero-days.
     * We left-fill from today back so the chart always has 30 data points.
     */
    private function dailySubmissions(int $institutionId): array
    {
        $startDate = Carbon::today()->subDays(29);

        // Pull submission counts from the database
        $raw = Application::whereHas('programme', function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            })
            ->where('submitted_at', '>=', $startDate)
            ->select(DB::raw('DATE(submitted_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Fill in missing days with 0 so the chart line is continuous
        $series = [];
        for ($i = 0; $i < 30; $i++) {
            $date = $startDate->copy()->addDays($i)->toDateString();
            $series[] = [
                'date'  => $date,
                'count' => $raw[$date] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * Number of applications in each status.
     */
    private function statusDistribution(int $institutionId): array
    {
        $counts = Application::whereHas('programme', function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            })
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Return all statuses, even those with zero, so the donut shape is stable
        $statuses = ['submitted', 'under_review', 'accepted', 'rejected', 'waitlisted'];
        $out = [];
        foreach ($statuses as $status) {
            $out[] = [
                'status' => $status,
                'count'  => $counts[$status] ?? 0,
            ];
        }
        return $out;
    }

    /**
     * Top programmes by application count.
     * Returns programme name + count, ordered by popularity.
     */
    private function programmePopularity(int $institutionId): array
    {
        return DB::table('applications')
            ->join('programmes', 'applications.programme_id', '=', 'programmes.id')
            ->where('programmes.institution_id', $institutionId)
            ->select('programmes.name as programme', DB::raw('COUNT(*) as count'))
            ->groupBy('programmes.id', 'programmes.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Applicant counts grouped by province (geographic distribution).
     */
    private function provinceDistribution(int $institutionId): array
    {
        return DB::table('applications')
            ->join('programmes', 'applications.programme_id', '=', 'programmes.id')
            ->join('users',      'applications.user_id',      '=', 'users.id')
            ->where('programmes.institution_id', $institutionId)
            ->whereNotNull('users.province')
            ->select('users.province', DB::raw('COUNT(*) as count'))
            ->groupBy('users.province')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }
}