<?php

namespace App\Http\Resources\Api;

use App\Models\Prescription;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape for a Prescription (REQ-API-6 + design §4 Resource
 * table).
 *
 * Renders the canonical set: id, appointment_id, doctor_id,
 * patient_id, unique_code, issued_at (in resolved TZ), status.
 */
class PrescriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Prescription $rx */
        $rx = $this->resource;

        $tzName = (string) ($request->attributes->get('tz')?->name ?? config('app.timezone'));

        $format = static function (\DateTimeInterface $value) use ($tzName): string {
            return CarbonImmutable::instance($value)
                ->setTimezone($tzName)
                ->toIso8601String();
        };

        return [
            'id' => $rx->id,
            'appointment_id' => $rx->appointment_id,
            'doctor_id' => $rx->doctor_id,
            'patient_id' => $rx->patient_id,
            'unique_code' => $rx->unique_code,
            'issued_at' => $rx->issued_at ? $format($rx->issued_at) : null,
            'status' => $rx->status,
        ];
    }
}
