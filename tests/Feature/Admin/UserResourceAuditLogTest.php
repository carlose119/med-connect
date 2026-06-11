<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Mail\InvitationActivated;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The admin-audit spec (openspec/changes/agenda-core/specs/admin-audit/spec.md)
 * requires every admin action to write an `audit_logs` row. The original
 * UserResource create flow forgot to do that, so these tests pin the wire
 * down: admin creates a doctor -> doctor.created log; admin creates any
 * other user -> user.created log.
 */
it('admin creating a doctor writes a doctor.created audit log row', function () {
    $admin = User::factory()->admin()->create();
    $specialty = Specialty::factory()->create();

    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Dr. Smith',
            'email' => 'smith@example.com',
            'role' => 'doctor',
            'specialty_id' => $specialty->id,
            'license_number' => 'LIC-001',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $doctor = Doctor::where('license_number', 'LIC-001')->first();
    expect($doctor)->not->toBeNull('Doctor row should have been written by the resource');

    $log = AuditLog::where('action', 'doctor.created')
        ->where('subject_type', Doctor::class)
        ->where('subject_id', $doctor->id)
        ->first();

    expect($log)->not->toBeNull('audit log row for the new doctor must exist')
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->actor_type)->toBe('admin')
        ->and($log->metadata)->toMatchArray([
            'user_id' => $doctor->user_id,
            'specialty_id' => $specialty->id,
            'license_number' => 'LIC-001',
        ]);
});

it('admin creating a patient writes a user.created audit log row', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Jane Patient',
            'email' => 'jane.patient@example.com',
            'password' => 'password',
            'role' => 'patient',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $newUser = User::where('email', 'jane.patient@example.com')->first();
    expect($newUser)->not->toBeNull('User row should have been written by the resource');

    $log = AuditLog::where('action', 'user.created')
        ->where('subject_type', User::class)
        ->where('subject_id', $newUser->id)
        ->first();

    expect($log)->not->toBeNull('audit log row for the new user must exist')
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->actor_type)->toBe('admin')
        ->and($log->metadata)->toMatchArray([
            'role' => 'patient',
            'email' => 'jane.patient@example.com',
        ]);
});

it('admin creating a doctor sends invitation email not temp password', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $specialty = Specialty::factory()->create();

    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Dr. Test',
            'email' => 'doctor.test@example.com',
            'role' => 'doctor',
            'specialty_id' => $specialty->id,
            'license_number' => 'MP-12345',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Mail::assertQueued(InvitationActivated::class, function ($mail) {
        return $mail->hasTo('doctor.test@example.com')
            && Str::isUuid($mail->invitationToken);
    });
});