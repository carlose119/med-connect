<?php

namespace App\Http\Resources\Api;

use App\Models\MedicalHistory;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape for a MedicalHistory (REQ-API-6 + design §4 Resource
 * table).
 *
 * Renders the canonical set: id, patient_id, primary_doctor_id,
 * opened_at (in resolved TZ), notes_count.
 */
class MedicalHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MedicalHistory $hist */
        $hist = $this->resource;

        $tzName = (string) ($request->attributes->get('tz')?->name ?? config('app.timezone'));

        $format = static function (\DateTimeInterface $value) use ($tzName): string {
            return CarbonImmutable::instance($value)
                ->setTimezone($tzName)
                ->toIso8601String();
        };

        return [
            'id' => $hist->id,
            'patient_id' => $hist->patient_id,
            'primary_doctor_id' => $hist->primary_doctor_id,
            'opened_at' => $hist->opened_at ? $format($hist->opened_at) : null,
            'notes_count' => $hist->notes_count,
        ];
    }
}
