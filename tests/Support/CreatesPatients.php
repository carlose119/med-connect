<?php

namespace Tests\Support;

use App\Models\Patient;
use App\Models\User;

/**
 * Test fixture: builds a patient (User with role=patient + Patient
 * profile + a Sanctum token) in one call. The Patient profile is
 * explicitly attached to the same User the token belongs to (the
 * default `Patient::factory()` would generate its own User).
 *
 * Returns the trio so callers can destructure:
 *   [$user, $patient, $token] = $this->createPatientWithToken();
 *
 * The token is a Sanctum `HasApiTokens` plaintext token, so the test
 * can either pass it via `Authorization: Bearer $token` or via
 * `actingAs($user, 'sanctum')` (the standard pattern in this suite).
 *
 * PR 2 uses this trait in BookAppointmentTest and CancelAppointmentTest
 * to wire a patient onto the Sanctum route group. The doctor half
 * (`CreatesDoctors`) provides the matching doctor + schedule.
 */
trait CreatesPatients
{
    /**
     * @return array{0: User, 1: Patient, 2: string}
     */
    public function createPatientWithToken(): array
    {
        $user = User::factory()->patient()->create();
        $patient = Patient::factory()->for($user)->create();

        $token = $user->createToken('test')->plainTextToken;

        return [$user, $patient, $token];
    }
}
