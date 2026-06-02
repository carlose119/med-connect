<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ListAuditLogsRequest;
use App\Http\Resources\Api\AuditLogResource;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * HTTP surface for the audit logs endpoint.
 *
 * `index()`  → GET /api/audit-logs  (paginated, admin-only)
 *
 * Admin-only gate is enforced inline because no AuditLogPolicy
 * exists yet. Non-admin actors receive 403 FORBIDDEN via the
 * standard AuthorizationException → PR 1 exception handler chain.
 */
class AuditLogController extends Controller
{
    public function index(ListAuditLogsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        if (! $user->isAdmin()) {
            throw new AuthorizationException(
                'Only administrators can view the audit log.'
            );
        }

        $query = AuditLog::query();
        $filters = $request->validated();

        if (! empty($filters['actor_id'])) {
            $query->where('user_id', (int) $filters['actor_id']);
        }
        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['from']));
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', CarbonImmutable::parse($filters['to']));
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        $paginator = $query
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return AuditLogResource::collection($paginator);
    }
}
