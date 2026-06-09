<?php

namespace App\Http\Controllers\Api;

use App\Actions\Medical\AmendMedicalNoteAction;
use App\Actions\Medical\CreateMedicalNoteAction;
use App\Http\Requests\Api\AmendMedicalNoteRequest;
use App\Http\Requests\Api\CreateMedicalNoteRequest;
use App\Http\Resources\Api\MedicalNoteResource;
use App\Models\MedicalHistory;
use App\Models\MedicalNote;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * HTTP surface for the medical-notes endpoint group.
 *
 * store()  → POST   /api/medical-histories/{history}/notes  (create)
 * index()  → GET    /api/medical-histories/{history}/notes  (list)
 * show()   → GET    /api/medical-notes/{note}               (single)
 * amend()  → POST   /api/medical-notes/{note}/amend         (amend)
 */
class MedicalNoteController extends Controller
{
    use AuthorizesRequests;

    public function store(
        CreateMedicalNoteRequest $request,
        MedicalHistory $medicalHistory,
        CreateMedicalNoteAction $action,
    ): JsonResponse {
        $this->authorize('createNote', $medicalHistory);

        $user = $request->user();

        $note = $action(
            $medicalHistory,
            $user->doctor,
            $request->validated(),
        );

        return (new MedicalNoteResource($note))
            ->response()
            ->setStatusCode(201);
    }

    public function index(MedicalHistory $medicalHistory): AnonymousResourceCollection
    {
        $notes = $medicalHistory->notes()
            ->orderByDesc('created_at')
            ->paginate(20);

        return MedicalNoteResource::collection($notes);
    }

    public function show(MedicalNote $medicalNote): MedicalNoteResource
    {
        $this->authorize('view', $medicalNote->medicalHistory);

        return new MedicalNoteResource($medicalNote);
    }

    public function amend(
        AmendMedicalNoteRequest $request,
        MedicalNote $medicalNote,
        AmendMedicalNoteAction $action,
    ): JsonResponse {
        $this->authorize('createNote', $medicalNote->medicalHistory);

        $amendment = $action(
            $medicalNote,
            $request->validated(),
        );

        return (new MedicalNoteResource($amendment))
            ->response()
            ->setStatusCode(201);
    }
}
