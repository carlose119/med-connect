<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\UploadMedicalAttachmentRequest;
use App\Http\Resources\Api\MedicalAttachmentResource;
use App\Models\MedicalAttachment;
use App\Models\MedicalNote;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * HTTP surface for the medical-attachments endpoint group.
 *
 * upload()  → POST   /api/medical-notes/{note}/attachments  (create)
 * index()   → GET    /api/medical-notes/{note}/attachments  (list)
 * destroy() → DELETE /api/medical-attachments/{attachment}  (delete)
 */
class MedicalAttachmentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Upload a file and attach it to the given medical note.
     */
    public function upload(
        UploadMedicalAttachmentRequest $request,
        MedicalNote $medicalNote,
    ): JsonResponse {
        $this->authorize('view', $medicalNote->medicalHistory);

        $file = $request->file('file');

        /** @var string $path Relative path on the clinical_attachments disk */
        $path = $file->store('attachments', 'clinical_attachments');

        $attachment = new MedicalAttachment;
        $attachment->medical_note_id = $medicalNote->id;
        $attachment->file_path = $path;
        $attachment->file_name = $file->getClientOriginalName();
        $attachment->mime_type = $file->getMimeType();
        $attachment->size_bytes = $file->getSize();
        $attachment->uploaded_by = $request->user()->id;
        $attachment->created_at = now();
        $attachment->save();

        return (new MedicalAttachmentResource($attachment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * List all attachments for the given medical note.
     */
    public function index(MedicalNote $medicalNote): AnonymousResourceCollection
    {
        $this->authorize('view', $medicalNote->medicalHistory);

        $attachments = $medicalNote->attachments()
            ->orderByDesc('created_at')
            ->get();

        return MedicalAttachmentResource::collection($attachments);
    }

    /**
     * Delete an attachment — only the uploader may do this.
     */
    public function destroy(Request $request, MedicalAttachment $medicalAttachment): JsonResponse
    {
        if ($medicalAttachment->uploaded_by !== $request->user()->id) {
            throw new AuthorizationException(
                'You can only delete your own attachments.'
            );
        }

        Storage::disk('clinical_attachments')->delete($medicalAttachment->file_path);
        $medicalAttachment->delete();

        return response()->json(['message' => 'Attachment deleted.']);
    }
}
