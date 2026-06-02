<?php

namespace App\Exceptions\Domain;

/**
 * Thrown when the requested slot is not in the published schedule, or
 * when the persistence layer rejects a second insert into the same
 * (doctor_id, start_time) for a non-cancelled row. The persistence
 * branch is the second line of defense behind `BookAppointmentAction`'s
 * `lockForUpdate()` row lock; either path converges on this exception.
 *
 * HTTP 409 (Conflict) — the request collides with the current state
 * of the target resource.
 *
 * The optional `existingAppointmentId` is the id of the appointment
 * row that is blocking the slot (only set by the slot-lookup guard
 * in BookAppointmentAction, not by the unique-index path). The error
 * envelope surfaces it under error.details.conflicting_appointment_id
 * so the HTTP client can fetch the winning appointment and surface
 * "this slot is taken" UX without a second round-trip.
 */
class SlotNotAvailableException extends DomainException
{
    public function __construct(
        string $message = 'Slot not available.',
        private readonly ?int $existingAppointmentId = null,
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function getExistingAppointmentId(): ?int
    {
        return $this->existingAppointmentId;
    }

    /**
     * Named constructor used by the action's slot-lookup guard when
     * it finds the blocking row in `appointments`. Carries the
     * winning appointment id into the exception so the error
     * envelope can include it.
     */
    public static function withConflict(
        int $existingAppointmentId,
        string $message = 'Slot not available.',
    ): self {
        return new self($message, $existingAppointmentId);
    }
}
