<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate the payload for POST /api/medical-notes/{note}/attachments.
 *
 * Rules:
 *   - file   : required, must be an uploaded file
 *   - mimes  : jpg, png, pdf, doc, docx
 *   - max    : 10 MB (10 240 KiB)
 */
class UploadMedicalAttachmentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,png,pdf,doc,docx', 'max:10240'],
        ];
    }
}
