<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Central write-path for the audit trail.
 *
 * Two guarantees:
 *  1. Every important mutation is attributable: who, what, which record,
 *     what changed, from which IP, when.
 *  2. Logging NEVER breaks the operation it records — failures are
 *     swallowed into the application log. A payment must not fail
 *     because the audit insert did.
 *
 * Usage:
 *   AuditLogger::log('application.decided', $application,
 *       old: ['status' => 'under_review'],
 *       new: ['status' => 'accepted', 'student_number' => '26100001']);
 */
class AuditLogger
{
    public static function log(
        string $action,
        ?Model $auditable = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
        ?int $institutionId = null,
    ): void {
        try {
            $request = request();
            $user    = $request?->user();

            AuditLog::create([
                'user_id'        => $userId ?? $user?->id,
                'institution_id' => $institutionId ?? $user?->institution_id,
                'action'         => $action,
                'auditable_type' => $auditable ? get_class($auditable) : null,
                'auditable_id'   => $auditable?->getKey(),
                'old_values'     => $old,
                'new_values'     => $new,
                'ip_address'     => $request?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Audit log write failed: ' . $e->getMessage(), [
                'action' => $action,
            ]);
        }
    }
}