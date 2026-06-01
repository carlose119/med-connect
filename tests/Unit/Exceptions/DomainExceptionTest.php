<?php

use App\Exceptions\Domain\AnticipationWindowViolationException;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\PatientOverlapException;
use App\Exceptions\Domain\SlotNotAvailableException;

/**
 * PR 4 unit tests for the 3 booking domain exceptions. Each maps to a
 * specific HTTP status so the future exception handler (or framework
 * render hook) can translate the domain failure to a response without
 * coupling the action layer to HTTP.
 */
it('SlotNotAvailableException maps to HTTP 409 (Conflict)', function () {
    $e = new SlotNotAvailableException('Slot at 10:00 is already booked.');

    expect($e)->toBeInstanceOf(DomainException::class)
        ->and($e->httpStatus())->toBe(409)
        ->and($e->getMessage())->toBe('Slot at 10:00 is already booked.');
});

it('AnticipationWindowViolationException maps to HTTP 422 (Unprocessable Entity)', function () {
    $e = new AnticipationWindowViolationException('Bookings need at least 2h of anticipación.');

    expect($e)->toBeInstanceOf(DomainException::class)
        ->and($e->httpStatus())->toBe(422)
        ->and($e->getMessage())->toBe('Bookings need at least 2h of anticipación.');
});

it('PatientOverlapException maps to HTTP 422 (Unprocessable Entity)', function () {
    $e = new PatientOverlapException('Patient has an overlapping non-cancelled appointment.');

    expect($e)->toBeInstanceOf(DomainException::class)
        ->and($e->httpStatus())->toBe(422)
        ->and($e->getMessage())->toBe('Patient has an overlapping non-cancelled appointment.');
});
