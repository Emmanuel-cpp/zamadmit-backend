<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Read-only activity feed for an institution's owners.
 * Tenancy-scoped; audit rows themselves are immutable.
 */
class AuditLogController extends Controller
{
    /**
     * GET /api/admin/audit-logs
     * Latest 200 events for the owner's institution, newest first.
     */
    public function index(Request $request)
    {
        $logs = AuditLog::where('institution_id', $request->user()->institution_id)
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json($logs);
    }
}