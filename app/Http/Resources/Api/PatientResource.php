<?php

namespace App\Http\Resources\Api;

use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape for a Patient (REQ-API-6 + design §4 Resource table).
 *
 * Renders the canonical set + the related User sub-object:
 *   - id, user_id, identification_number, phone, birth_date, gender
 *   - user sub-object: {id, name, email}
 */
class PatientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Patient $pat */
        $pat = $this->resource;

        $tzName = (string) ($request->attributes->get('tz')?->name ?? config('app.timezone'));

        $format = static function (\DateTimeInterface $value) use ($tzName): string {
            return CarbonImmutable::instance($value)
                ->setTimezone($tzName)
                ->toDateString();
        };

        return [
            'id' => $pat->id,
            'user_id' => $pat->user_id,
            'identification_number' => $pat->identification_number,
            'phone' => $pat->phone,
            'birth_date' => $pat->birth_date ? $format($pat->birth_date) : null,
            'gender' => $pat->gender,
            'user' => $pat->user ? [
                'id' => $pat->user->id,
                'name' => $pat->user->name,
                'email' => $pat->user->email,
            ] : null,
        ];
    }
}
