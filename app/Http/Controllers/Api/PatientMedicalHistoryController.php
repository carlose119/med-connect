<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\MedicalHistoryResource;
use App\Models\MedicalHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * HTTP surface for patient-facing medical history endpoints (mobile app).
 *
 * show()  → GET /api/v1/medical-history          (own history, auto-discovered)
 * notes() → GET /api/v1/medical-history/notes   (own notes, auto-discovered)
 *
 * Authz: MedicalHistoryPolicy@view restricts to the owning patient.
 */
class PatientMedicalHistoryController extends Controller
{
    /**
     * Returns the medical history for the authenticated patient.
     * Auto-discovers the history from the patient's user record — no ID needed.
     *
     * GET /api/v1/medical-history
     */
    public function show(): MedicalHistoryResource|JsonResponse
    {
        $user = auth()->user();

        if (! $user->isPatient()) {
            return response()->json(['message' => 'Solo pacientes pueden acceder a su historial.'], 403);
        }

        $history = MedicalHistory::where('patient_id', $user->patient->id ?? 0)->first();

        if (! $history) {
            return response()->json(['message' => 'No tenés un historial clínico registrado aún.'], 404);
        }

        Gate::authorize('view', $history);

        $history->loadCount('notes');

        return new MedicalHistoryResource($history);
    }

    /**
     * Returns the clinical notes for the authenticated patient's history.
     * Auto-discovers the history from the patient's user record.
     *
     * GET /api/v1/medical-history/notes
     */
    public function notes(): AnonymousResourceCollection|JsonResponse
    {
        $user = auth()->user();

        if (! $user->isPatient()) {
            return response()->json(['message' => 'Solo pacientes pueden acceder a su historial.'], 403);
        }

        $history = MedicalHistory::where('patient_id', $user->patient->id ?? 0)->first();

        if (! $history) {
            return response()->json(['message' => 'No tenés un historial clínico registrado aún.'], 404);
        }

        Gate::authorize('view', $history);

        $notes = $history->notes()
            ->orderByDesc('created_at')
            ->paginate(20);

        return \App\Http\Resources\Api\MedicalNoteResource::collection($notes);
    }
}