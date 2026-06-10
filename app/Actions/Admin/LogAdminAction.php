<?php

namespace App\Actions\Admin;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Persist an audit log row for an admin action.
 *
 * Always records `actor_type = 'admin'`. Uses `class_basename()` to
 * store short class names (e.g. 'Doctor' instead of 'App\Models\Doctor').
 * Falls back to `request()->ip()` when no IP is provided.
 */
class LogAdminAction
{
    public function __invoke(
        User $user,
        string $action,
        string $subjectType,
        int $subjectId,
        array $metadata = [],
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user->id,
            'actor_type' => 'admin',
            'action' => $action,
            'subject_type' => class_basename($subjectType),
            'subject_id' => $subjectId,
            'metadata' => $metadata,
            'ip_address' => $ipAddress ?? request()->ip(),
        ]);
    }
}