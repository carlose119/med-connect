<?php

namespace App\Http\Controllers\Api;

use App\Actions\Medical\CancelPrescriptionAction;
use App\Actions\Medical\IssuePrescriptionAction;
use App\Http\Requests\Api\CreatePrescriptionRequest;
use App\Http\Requests\Api\ListPrescriptionsRequest;
use App\Http\Requests\Api\UpdatePrescriptionRequest;
use App\Http\Resources\Api\PrescriptionResource;
use App\Models\Appointment;
use App\Models\Prescription;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * HTTP surface for the prescriptions endpoint group.
 *
 * `index()`  → GET    /api/prescriptions             (paginated, role-scoped)
 * `store()`  → POST   /api/prescriptions             (issue new prescription)
 * `show()`   → GET    /api/prescriptions/{prescription} (view single)
 * `update()` → PUT    /api/prescriptions/{prescription} (cancel prescription)
 *
 * Role-scope rules (REQ-API-6):
 *   - patient  → only their own prescriptions (patient_id = $user->patient->id)
 *   - doctor   → only prescriptions they issued (doctor_id = $user->doctor->id)
 *   - admin    → all prescriptions
 */
class PrescriptionController extends Controller
{
    use AuthorizesRequests;

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
            ->with('items')
            ->orderBy('issued_at')
            ->paginate($perPage);

        return PrescriptionResource::collection($paginator);
    }

    public function store(
        CreatePrescriptionRequest $request,
        IssuePrescriptionAction $action,
    ): JsonResponse {
        $user = $request->user();
        $validated = $request->validated();

        /** @var Appointment $appointment */
        $appointment = Appointment::query()->findOrFail($validated['appointment_id']);

        // Validate the authenticated doctor owns the appointment
        if ($user->doctor?->id !== $appointment->doctor_id) {
            throw new AuthorizationException('You are not authorised to issue a prescription for this appointment.');
        }

        try {
            $prescription = $action(
                $appointment,
                $user->doctor,
                $validated['items'],
            );
        } catch (\InvalidArgumentException $e) {
            // Appointment not in 'completed' state
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->getMessage(),
                    'details' => ['appointment_id' => ['The appointment must be completed before issuing a prescription.']],
                ],
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violation on unique_code.
            // Extract driver code from exception (works for PDOException with
            // errorInfo or for manually constructed exceptions).
            $driverCode = $e->errorInfo[1]
                ?? $e->getPrevious()?->getCode()
                ?? $e->getCode();
            $isUniqueViolation = in_array((int) $driverCode, [23505, 19, 1062], true);
            if ($isUniqueViolation && str_contains($e->getMessage(), 'unique_code')) {
                return response()->json([
                    'error' => [
                        'code' => 'UNIQUE_CODE_COLLISION',
                        'message' => 'A prescription with the same code already exists. Please retry.',
                    ],
                ], 409);
            }
            throw $e;
        }

        return (new PrescriptionResource($prescription))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Prescription $prescription): PrescriptionResource
    {
        $this->authorize('view', $prescription);

        $prescription->load('items');

        return new PrescriptionResource($prescription);
    }

    public function update(
        UpdatePrescriptionRequest $request,
        Prescription $prescription,
        CancelPrescriptionAction $action,
    ): JsonResponse {
        $this->authorize('update', $prescription);

        $validated = $request->validated();

        $prescription = $action($prescription, $validated['cancellation_reason']);

        return (new PrescriptionResource($prescription))
            ->response()
            ->setStatusCode(200);
    }
}
