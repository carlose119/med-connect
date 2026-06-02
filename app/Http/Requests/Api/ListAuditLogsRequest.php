<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wire payload for GET /api/audit-logs (REQ-API-6 + design §3).
 *
 * Filters:
 *   - actor_id     : nullable, exists in `users`
 *   - action       : nullable, free-text (e.g. 'user.created')
 *   - subject_type : nullable, free-text (e.g. 'User' or FQN)
 *   - from / to    : nullable, date (created_at between)
 *   - per_page     : nullable, integer, 1..100 (default 20)
 *
 * The controller enforces admin-only (no `authorize` here — the
 * isAdmin() check is in the controller because there is no
 * AuditLogPolicy).
 */
class ListAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'actor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'action' => ['nullable', 'string', 'max:96'],
            'subject_type' => ['nullable', 'string', 'max:191'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
