<?php

namespace App\Http\Resources\Api;

use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape for a Doctor (REQ-API-11 + REQ-API-12).
 *
 * Renders:
 *   - id
 *   - user_id
 *   - specialty_id + specialty sub-object {id, name, slug}
 *   - license_number
 *   - bio
 *   - user sub-object {id, name, email}
 *
 * The `user` and `specialty` sub-objects are eager-loaded by the
 * controller's `with(['user', 'specialty'])` to keep the response
 * build O(1) queries (no N+1).
 */
class DoctorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Doctor $doc */
        $doc = $this->resource;

        return [
            'id' => $doc->id,
            'user_id' => $doc->user_id,
            'specialty_id' => $doc->specialty_id,
            'license_number' => $doc->license_number,
            'bio' => $doc->bio,
            'specialty' => $doc->specialty ? [
                'id' => $doc->specialty->id,
                'name' => $doc->specialty->name,
                'slug' => $doc->specialty->slug,
            ] : null,
            'user' => $doc->user ? [
                'id' => $doc->user->id,
                'name' => $doc->user->name,
                'email' => $doc->user->email,
            ] : null,
        ];
    }
}
