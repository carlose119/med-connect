<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wire payload for GET /api/doctors (REQ-API-6 + design §3).
 *
 * Filters:
 *   - specialty_id : nullable, exists in `specialties`
 *   - q            : nullable, free-text name LIKE %q%
 *   - per_page     : nullable, integer, 1..100 (default 20)
 */
class ListDoctorsRequest extends FormRequest
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
            'specialty_id' => ['nullable', 'integer', Rule::exists('specialties', 'id')],
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
