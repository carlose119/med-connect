<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wire payload for POST /api/auth/login (REQ-API-7 scenario 1).
 *
 * The route is public (no `auth:sanctum` middleware) so `authorize()`
 * returns `true` for all callers; the controller runs
 * `Auth::guard('web')->attempt()` and maps a failed attempt to a
 * thrown `AuthenticationException` (rendered as 401 UNAUTHENTICATED
 * by the PR 1 exception handler).
 *
 * Field rules:
 *   - email        : required, valid email format
 *   - password     : required, string
 *   - device_name? : optional, free-text, capped at 64 chars.
 *                    The Sanctum token is named after the device so
 *                    `personal_access_tokens.name` shows the source
 *                    when revoking via `User::tokens()->delete()`.
 *
 * Deliberately NOT adding `exists:users,email` — a non-existent
 * email should map to 401 UNAUTHENTICATED (same as a wrong
 * password) to avoid leaking which addresses are registered.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The login endpoint is public; anyone may attempt to log in.
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:64'],
        ];
    }
}
