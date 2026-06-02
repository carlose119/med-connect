<?php

namespace App\Http\Resources\Api;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape for an AuditLog (REQ-API-6 + design §4 Resource table).
 *
 * Renders the canonical set: id, user_id, actor_type, action,
 * subject_type, subject_id, metadata, ip_address, created_at.
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuditLog $log */
        $log = $this->resource;

        $tzName = (string) ($request->attributes->get('tz')?->name ?? config('app.timezone'));

        $format = static function ($value) use ($tzName): ?string {
            if ($value === null || $value === '') {
                return null;
            }
            if (! $value instanceof \DateTimeInterface) {
                $value = \Carbon\CarbonImmutable::parse((string) $value);
            }
            return $value->setTimezone($tzName)->toIso8601String();
        };

        return [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'actor_type' => $log->actor_type,
            'action' => $log->action,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'metadata' => $log->metadata,
            'ip_address' => $log->ip_address,
            'created_at' => $format($log->created_at),
        ];
    }
}
