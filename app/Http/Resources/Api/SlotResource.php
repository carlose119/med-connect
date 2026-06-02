<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape for a single slot in the doctor's published availability
 * list (REQ-API-12 + design §4 "SlotResource" entry).
 *
 * Wraps an array of the form `['start' => CarbonImmutable, 'end' =>
 * CarbonImmutable]`. The slot boundaries are stored in UTC in the
 * service; the resource formats them in the resolved TZ (the
 * `?tz=` override or the consultorio default).
 *
 * Wire keys are `start_time` and `end_time` to mirror the
 * `AppointmentResource` shape — clients can compare a slot's
 * `start_time` to an appointment's `start_time` directly.
 */
class SlotResource extends JsonResource
{
    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        $tzName = (string) ($request->attributes->get('tz')?->name ?? config('app.timezone'));

        $format = static function (\DateTimeInterface $value) use ($tzName): string {
            return \Carbon\CarbonImmutable::instance($value)
                ->setTimezone($tzName)
                ->toIso8601String();
        };

        return [
            'start_time' => $format($this->resource['start']),
            'end_time' => $format($this->resource['end']),
        ];
    }
}
