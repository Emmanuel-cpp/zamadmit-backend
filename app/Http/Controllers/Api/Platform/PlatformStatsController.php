<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\Payment;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Platform-wide operational statistics and the global audit feed.
 * Aggregates only — no applicant personal data is exposed here.
 */
class PlatformStatsController extends Controller
{
    /**
     * GET /api/platform/stats
     */
    public function index()
    {
        return response()->json([
            'institutions_total'     => Institution::count(),
            'institutions_active'    => Institution::where('is_suspended', false)->count(),
            'institutions_suspended' => Institution::where('is_suspended', true)->count(),

            'students'     => User::where('role', 'student')->count(),
            'admins'       => User::where('role', 'institution_admin')->count(),
            'programmes'   => Programme::count(),
            'applications' => Application::where('status', '!=', 'draft')->count(),

            // Revenue: platform commission actually collected
            'payments_completed' => Payment::where('status', 'completed')->count(),
            'platform_revenue'   => (float) Payment::where('status', 'completed')->sum('platform_fee'),
            'gross_processed'    => (float) Payment::where('status', 'completed')->sum('amount'),
        ]);
    }

    /**
     * GET /api/platform/audit-logs
     * Global audit feed across every institution.
     */
    public function auditLogs(Request $request)
    {
        $logs = AuditLog::with(['user:id,first_name,last_name,email'])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json($logs);
    }
}