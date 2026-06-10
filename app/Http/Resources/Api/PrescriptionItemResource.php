<?php

namespace App\Http\Resources\Api;

use App\Models\PrescriptionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape for a PrescriptionItem.
 *
 * Renders: id, name, dosage, frequency, duration, position.
 */
class PrescriptionItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PrescriptionItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'name' => $item->name,
            'dosage' => $item->dosage,
            'frequency' => $item->frequency,
            'duration' => $item->duration,
            'position' => $item->position,
        ];
    }
}