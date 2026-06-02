<?php

namespace App\Http\Controllers\Api;

use App\Actions\BookAppointmentAction;
use App\Clinic\Timezone;
use App\Http\Requests\Api\BookAppointmentRequest;
use App\Http\Resources\Api\AppointmentResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
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
}
