<?php

namespace App\Http\Resources\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape for a User (REQ-API-3 + REQ-API-15).
 *
 * Renders the canonical auth surface:
 *   - id, name, email, role
 *
 * Explicit deny-list (NEVER include):
 *   - password
 *   - remember_token
 *   - email_verified_at
 *   - created_at / updated_at
 *
 * The model already has `password` + `remember_token` in the
 * #[Hidden] attribute (User.php) and Laravel auto-hides them in
 * toArray(). The resource's job is to ALSO hide `email_verified_at`
 * and timestamps so the wire shape is minimal and stable across
 * future migrations.
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}
