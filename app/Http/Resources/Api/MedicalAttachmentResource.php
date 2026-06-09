<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * JSON shape for a MedicalAttachment.
 *
 * Renders:
 *   - id
 *   - note_id
 *   - filename
 *   - mime
 *   - size
 *   - url (generated from the storage disk)
 *   - created_at (ISO 8601)
 */
class MedicalAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note_id' => $this->medical_note_id,
            'filename' => $this->file_name,
            'mime' => $this->mime_type,
            'size' => $this->size_bytes,
            'url' => Storage::disk('clinical_attachments')->url($this->file_path),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
