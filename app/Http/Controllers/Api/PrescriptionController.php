<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ListPrescriptionsRequest;
use App\Http\Resources\Api\PrescriptionResource;
use App\Models\Prescription;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * HTTP surface for the prescriptions endpoint group.
 *
 * `index()`  → GET /api/prescriptions  (paginated, role-scoped)
 *
 * Role-scope rules (REQ-API-6):
 *   - patient  → only their own prescriptions (patient_id = $user->patient->id)
 *   - doctor   → only prescriptions they issued (doctor_id = $user->doctor->id)
 *   - admin    → all prescriptions
 */
class PrescriptionController extends Controller
{
    public function index(ListPrescriptionsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Prescription::query();
        $filters = $request->validated();

        if ($user->isPatient() && $user->patient) {
            $query->where('patient_id', $user->patient->id);
        } elseif ($user->isDoctor() && $user->doctor) {
            $query->where('doctor_id', $user->doctor->id);
        }
        // admin: no scope

        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }
        if (! empty($filters['from'])) {
            $query->where('issued_at', '>=', CarbonImmutable::parse($filters['from']));
        }
        if (! empty($filters['to'])) {
            $query->where('issued_at', '<=', CarbonImmutable::parse($filters['to']));
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        $paginator = $query
            ->orderBy('issued_at')
            ->paginate($perPage);

        return PrescriptionResource::collection($paginator);
    }
}
