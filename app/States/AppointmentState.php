<?php

namespace App\States;

/**
 * Backed enum mirror of the Appointment lifecycle. The spatie state machine
 * reads/writes the underlying `appointments.state` column as a string; this
 * enum is the canonical list of those values and is used in domain code where
 * a pure enum (no transition logic) is convenient — e.g. seeders, factories,
 * validation, Filament form options.
 *
 * The actual transition logic lives in the concrete state classes under
 * `App\States\Appointment\` and the actor-checked transitions under
 * `App\States\Transitions\`.
 */
enum AppointmentState: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
}
