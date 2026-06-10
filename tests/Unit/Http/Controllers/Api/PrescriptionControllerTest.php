<?php

namespace Tests\Unit\Http\Controllers\Api;

use App\Actions\Medical\IssuePrescriptionAction;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Requests\Api\CreatePrescriptionRequest;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Tests\TestCase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(TestCase::class, RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * Unit tests for PrescriptionController.
 *
 * Tests the controller's exception handling for the 409 collision path.
 */
describe('PrescriptionController', function (): void {
    describe('store() — unique code collision', function (): void {
        it('returns 409 when IssuePrescriptionAction throws unique constraint QueryException', function (): void {
            [$patientUser, $patient] = $this->createPatientWithToken();
            [$doctorUser, $doctor] = $this->createDoctorWithToken();

            $appointment = Appointment::factory()
                ->for($doctor)
                ->for($patient)
                ->create(['state' => 'completed']);

            // Mock the action to throw a QueryException with SQLite unique violation (code 19)
            $mockAction = Mockery::mock(IssuePrescriptionAction::class);
            $mockAction->shouldReceive('__invoke')
                ->andThrow(new QueryException(
                    'sqlite',
                    'INSERT INTO prescriptions ...',
                    [],
                    new \PDOException('UNIQUE constraint failed: prescriptions.unique_code', 19),
                ));

            $this->app->instance(IssuePrescriptionAction::class, $mockAction);

            $request = Mockery::mock(CreatePrescriptionRequest::class);
            $request->shouldReceive('user')->andReturn($doctorUser);
            $request->shouldReceive('validated')->andReturn([
                'appointment_id' => $appointment->id,
                'items' => [['name' => 'Drug', 'dosage' => '100mg', 'frequency' => 'daily', 'duration' => '7 days']],
            ]);

            $controller = new PrescriptionController();
            $response = $controller->store($request, $mockAction);

            expect($response)->toBeInstanceOf(JsonResponse::class);
            expect($response->getStatusCode())->toBe(409);
            $content = json_decode($response->getContent(), true);
            expect($content['error']['code'])->toBe('UNIQUE_CODE_COLLISION');
        });
    });

    describe('store() — non-completed appointment', function (): void {
        it('returns 422 when IssuePrescriptionAction throws InvalidArgumentException', function (): void {
            [$patientUser, $patient] = $this->createPatientWithToken();
            [$doctorUser, $doctor] = $this->createDoctorWithToken();

            $appointment = Appointment::factory()
                ->for($doctor)
                ->for($patient)
                ->create(['state' => 'completed']);

            // Mock the action to throw InvalidArgumentException (appointment not completed)
            $mockAction = Mockery::mock(IssuePrescriptionAction::class);
            $mockAction->shouldReceive('__invoke')
                ->andThrow(new \InvalidArgumentException(
                    'Cannot issue a prescription for an appointment that is not completed. Current state: App\States\Appointment\Pending'
                ));

            $this->app->instance(IssuePrescriptionAction::class, $mockAction);

            $request = Mockery::mock(CreatePrescriptionRequest::class);
            $request->shouldReceive('user')->andReturn($doctorUser);
            $request->shouldReceive('validated')->andReturn([
                'appointment_id' => $appointment->id,
                'items' => [['name' => 'Drug', 'dosage' => '100mg', 'frequency' => 'daily', 'duration' => '7 days']],
            ]);

            $controller = new PrescriptionController();
            $response = $controller->store($request, $mockAction);

            expect($response)->toBeInstanceOf(JsonResponse::class);
            expect($response->getStatusCode())->toBe(422);
            $content = json_decode($response->getContent(), true);
            expect($content['error']['code'])->toBe('VALIDATION_ERROR');
        });
    });

    describe('store() — doctor does not own appointment', function (): void {
        it('throws AuthorizationException when doctor does not own the appointment', function (): void {
            [$patientUser, $patient] = $this->createPatientWithToken();
            [$doctorUser, $doctor] = $this->createDoctorWithToken();

            // Another doctor (owns the appointment, but the requesting user doesn't)
            $otherDoctorUser = User::factory()->doctor()->create();
            $otherDoctor = Doctor::factory()->for($otherDoctorUser)->create();

            $appointment = Appointment::factory()
                ->for($otherDoctor)
                ->for($patient)
                ->create(['state' => 'completed']);

            $mockAction = Mockery::mock(IssuePrescriptionAction::class);

            // Mock the request with the requesting user (not the appointment owner)
            $request = Mockery::mock(CreatePrescriptionRequest::class);
            $request->shouldReceive('user')->andReturn($doctorUser);
            $request->shouldReceive('validated')->andReturn([
                'appointment_id' => $appointment->id,
                'items' => [['name' => 'Drug', 'dosage' => '100mg', 'frequency' => 'daily', 'duration' => '7 days']],
            ]);

            // Gate must be mocked to allow the create policy check
            \Gate::define('create', fn () => true);

            $controller = new PrescriptionController();

            expect(fn () => $controller->store($request, $mockAction))
                ->toThrow(AuthorizationException::class);
        });
    });
});