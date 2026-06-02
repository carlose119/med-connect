<?php

namespace App\Http\Resources\Api;

use App\Models\Appointment;
use App\States\Appointment\AppointmentState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape for an Appointment (REQ-API-9 + REQ-API-10).
 *
 * Renders:
 *   - id
 *   - doctor_id + patient_id (the FKs — denormalised for client convenience)
 *   - state                  (the short class name, e.g. "pending")
 *   - start_time / end_time  (ISO 8601 with the resolved offset — the
 *                             request's TZ was stashed on attributes
 *                             by the ResolveTimezone middleware in PR 1)
 *   - notes, cancellation_reason
 *   - doctor / patient       (id + name sub-objects)
 *   - created_at             (ISO 8601 in the resolved TZ)
 *
 * Wraps the `Appointment` model. The `$this->resource` is the
 * underlying model (Laravel's JsonResource convention).
 */
class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Appointment $appt */
        $appt = $this->resource;

        // The resolved TZ is stashed on the request attribute bag by
        // the PR 1 ResolveTimezone middleware. Falls back to the
        // local app timezone when missing (defensive — should not
        // happen for requests that went through the api/* group).
        $tzName = (string) ($request->attributes->get('tz')?->name ?? config('app.timezone'));

        $state = $appt->state;
        $stateName = $state instanceof AppointmentState
            ? (string) $state::$name
            : (string) $state;

        $format = static function (\DateTimeInterface $value) use ($tzName): string {
            return \Carbon\CarbonImmutable::instance($value)
                ->setTimezone($tzName)
                ->toIso8601String();
        };

        return [
            'id' => $appt->id,
            'doctor_id' => $appt->doctor_id,
            'patient_id' => $appt->patient_id,
            'state' => $stateName,
            'start_time' => $format($appt->start_time),
            'end_time' => $format($appt->end_time),
            'notes' => $appt->notes,
            'cancellation_reason' => $appt->cancellation_reason,
            'doctor' => [
                'id' => $appt->doctor?->id,
                'name' => $appt->doctor?->user?->name,
            ],
            'patient' => [
                'id' => $appt->patient?->id,
                'name' => $appt->patient?->user?->name,
            ],
            'created_at' => $appt->created_at ? $format($appt->created_at) : null,
        ];
    }
}
