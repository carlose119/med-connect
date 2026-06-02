<?php

namespace App\Http\Controllers\Api;

use App\Actions\BookAppointmentAction;
use App\Actions\CancelAppointmentAction;
use App\Clinic\Timezone;
use App\Http\Requests\Api\BookAppointmentRequest;
use App\Http\Requests\Api\CancelAppointmentRequest;
use App\Http\Requests\Api\ListAppointmentsRequest;
use App\Http\Resources\Api\AppointmentResource;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * Thin HTTP surface for the appointments endpoint group. PR 2 wires
 * the two mutation methods; PR 3 adds index/show.
 *
 * `store()`  → POST   /api/appointments        (BookAppointmentAction)
 * `cancel()` → DELETE /api/appointments/{id}   (CancelAppointmentAction) [PR 2]
 *
 * The TZ resolution comes from the `ResolveTimezone` middleware in
 * PR 1, which stashed the resolved `Timezone` value object on
 * `$request->attributes['tz']`. The controller forwards the local
 * `start_time` to the action via `$tz->toUtc(CarbonImmutable::parse(...))`
 * so the action layer (and the DB column) always see UTC.
 */
class AppointmentController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/appointments — paginated, role-scoped list.
     *
     * Role-scope rules (REQ-API-6 + design §"Auth + RBAC"):
     *   - patient  → where('patient_id', $user->patient->id)
     *   - doctor   → where('doctor_id', $user->doctor->id)
     *   - admin    → no extra scope
     *
     * Optional filters (from/to/doctor_id/patient_id/state) are applied
     * AFTER the role scope so the scope is always enforced.
     * Order is by `start_time` ASC for stable pagination.
     */
    public function index(ListAppointmentsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Appointment::query();

        if ($user->isPatient() && $user->patient) {
            $query->where('patient_id', $user->patient->id);
        } elseif ($user->isDoctor() && $user->doctor) {
            $query->where('doctor_id', $user->doctor->id);
        }
        // admin: no scope

        $filters = $request->validated();

        if (! empty($filters['from'])) {
            $query->where('start_time', '>=', CarbonImmutable::parse($filters['from']));
        }
        if (! empty($filters['to'])) {
            $query->where('start_time', '<=', CarbonImmutable::parse($filters['to']));
        }
        if (! empty($filters['doctor_id'])) {
            $query->where('doctor_id', (int) $filters['doctor_id']);
        }
        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }
        if (! empty($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        $paginator = $query
            ->orderBy('start_time')
            ->paginate($perPage);

        return AppointmentResource::collection($paginator);
    }

    /**
     * GET /api/appointments/{appointment} — single appointment detail.
     *
     * The authz gate is the AppointmentPolicy@view (admin, assigned
     * doctor, or assigned patient). A non-owner patient gets
     * 403 FORBIDDEN via the PR 1 exception handler.
     */
    public function show(Appointment $appointment): AppointmentResource
    {
        $this->authorize('view', $appointment);

        return new AppointmentResource($appointment);
    }

    public function store(BookAppointmentRequest $request, BookAppointmentAction $action): JsonResponse
    {
        $validated = $request->validated();

        // Resolve the patient id. Patients are auto-resolved to their
        // own profile; admin / doctor actors must supply it (enforced
        // by BookAppointmentRequest::rules()).
        $user = $request->user();
        $patientId = $user->isPatient()
            ? $user->patient->id
            : (int) $validated['patient_id'];

        // Convert the wire-format local datetime to UTC. The
        // action's slot lookup is keyed on UTC; the column itself
        // is also UTC.
        $tz = $request->attributes->get('tz') instanceof Timezone
            ? $request->attributes->get('tz')
            : new Timezone((string) config('clinic.timezone'));
        $startUtc = $tz->toUtc(CarbonImmutable::parse($validated['start_time']));

        $appointment = $action(
            (int) $validated['doctor_id'],
            $startUtc,
            $patientId,
            $validated['notes'] ?? null,
        );

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        // Policy gate: admin, assigned doctor, or assigned patient.
        $this->authorize('cancel', $appointment);

        $actor = $request->user();
        app(CancelAppointmentAction::class)(
            $appointment->id,
            $actor,
            $request->validated('reason'),
        );

        return (new AppointmentResource($appointment->refresh()))
            ->response()
            ->setStatusCode(200);
    }
}

