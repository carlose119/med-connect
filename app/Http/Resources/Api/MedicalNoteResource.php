<?php

namespace App\Http\Resources\Api;

use App\Models\MedicalNote;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape for a MedicalNote.
 *
 * Renders:
 *   - id
 *   - medical_history_id
 *   - doctor (id + name sub-object)
 *   - symptoms, physical_exam, diagnosis, treatment_notes
 *   - corrects_note_id (null for original notes)
 *   - created_at (ISO 8601 in the resolved TZ)
 */
class MedicalNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MedicalNote $note */
        $note = $this->resource;

        $tzName = (string) ($request->attributes->get('tz')?->name ?? config('app.timezone'));

        $format = static function (\DateTimeInterface $value) use ($tzName): string {
            return CarbonImmutable::instance($value)
                ->setTimezone($tzName)
                ->toIso8601String();
        };

        return [
            'id' => $note->id,
            'medical_history_id' => $note->medical_history_id,
            'doctor' => [
                'id' => $note->doctor?->id,
                'name' => $note->doctor?->user?->name,
            ],
            'symptoms' => $note->symptoms,
            'physical_exam' => $note->physical_exam,
            'diagnosis' => $note->diagnosis,
            'treatment_notes' => $note->treatment_notes,
            'corrects_note_id' => $note->corrects_note_id,
            'created_at' => $note->created_at ? $format($note->created_at) : null,
        ];
    }
}
