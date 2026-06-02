<?php

namespace App\Http\Resources\Api;

use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape for a Specialty (REQ-API-6 + design §4 Resource table).
 *
 * Renders the canonical set: id, name, slug, is_active.
 */
class SpecialtyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Specialty $spec */
        $spec = $this->resource;

        return [
            'id' => $spec->id,
            'name' => $spec->name,
            'slug' => $spec->slug,
            'is_active' => (bool) $spec->is_active,
        ];
    }
}
