<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\MedicalHistoryResource;
use App\Models\Appointment;
use App\Models\MedicalHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * HTTP surface for the medical histories endpoint group.
 *
 * `show()`  → GET /api/medical-histories/{medical_history}
 *
 * Authz is inline (no dedicated MedicalHistoryPolicy exists yet):
 *   - admin                       → 200
 *   - self patient                → 200
 *   - doctor with an appointment  → 200
 *   - other patient / other doctor → 403 FORBIDDEN
 */
class MedicalHistoryController extends Controller
{
    public function show(Request $request, MedicalHistory $medicalHistory): MedicalHistoryResource
    {
        $user = $request->user();
        $patient = $medicalHistory->patient;

        $allowed = match (true) {
            $user->isAdmin() => true,
            $user->isPatient() => $user->id === $patient->user_id,
            $user->isDoctor() => Appointment::query()
                ->where('doctor_id', $user->doctor->id)
                ->where('patient_id', $patient->id)
                ->exists(),
            default => false,
        };

        if (! $allowed) {
            throw new AuthorizationException(
                'You are not authorised to view this medical history.'
            );
        }

        return new MedicalHistoryResource($medicalHistory);
    }
}
