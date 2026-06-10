<?php

use App\Filament\Resources\DoctorScheduleOverrides\Pages\CreateDoctorScheduleOverride;
use App\Filament\Resources\DoctorScheduleOverrides\Pages\EditDoctorScheduleOverride;
use App\Filament\Resources\DoctorScheduleOverrides\Pages\ListDoctorScheduleOverrides;
use App\Models\Doctor;
use App\Models\DoctorScheduleOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Task 5.7: Override CRUD ───────────────────────────────────────

// ─── List: doctor scoping ───────────────────────────────────────────

it('lists only the doctors own overrides on the list page', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    $otherUser = User::factory()->doctor()->create();
    $otherDoctor = Doctor::factory()->for($otherUser)->create();
    $otherOverride = DoctorScheduleOverride::factory()->for($otherDoctor)->create([
        'date' => now()->addDays(5)->toDateString(),
        'type' => 'block',
    ]);

    $ownOverride = DoctorScheduleOverride::factory()->for($doctor)->create([
        'date' => now()->addDays(3)->toDateString(),
        'type' => 'extra_availability',
    ]);

    Livewire::actingAs($doctorUser)
        ->test(ListDoctorScheduleOverrides::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(collect([$ownOverride]))
        ->assertCanNotSeeTableRecords(collect([$otherOverride]));
});

// ─── Create block override ──────────────────────────────────────────

it('can create a block override with start and end times', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    Livewire::actingAs($doctorUser)
        ->test(CreateDoctorScheduleOverride::class)
        ->fillForm([
            'date' => now()->addDays(10)->toDateString(),
            'type' => 'block',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'reason' => 'Staff meeting',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('doctor_schedule_overrides', [
        'doctor_id' => $doctor->id,
        'type' => 'block',
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'reason' => 'Staff meeting',
    ]);
});

// ─── Create extra_availability override ─────────────────────────────

it('can create an extra_availability override', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    Livewire::actingAs($doctorUser)
        ->test(CreateDoctorScheduleOverride::class)
        ->fillForm([
            'date' => now()->addDays(10)->toDateString(),
            'type' => 'extra_availability',
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'reason' => 'Extended hours',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('doctor_schedule_overrides', [
        'doctor_id' => $doctor->id,
        'type' => 'extra_availability',
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
    ]);
});

// ─── Create full-day block override ─────────────────────────────────

it('can create a full-day block override with null times', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    Livewire::actingAs($doctorUser)
        ->test(CreateDoctorScheduleOverride::class)
        ->fillForm([
            'date' => now()->addDays(10)->toDateString(),
            'type' => 'block',
            'start_time' => null,
            'end_time' => null,
            'reason' => 'Public holiday',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('doctor_schedule_overrides', [
        'doctor_id' => $doctor->id,
        'type' => 'block',
        'start_time' => null,
        'end_time' => null,
    ]);
});

// ─── Edit ───────────────────────────────────────────────────────────

it('can update the date or type of an override', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $override = DoctorScheduleOverride::factory()->for($doctor)->create([
        'type' => 'block',
        'date' => now()->addDays(5)->toDateString(),
    ]);

    Livewire::actingAs($doctorUser)
        ->test(EditDoctorScheduleOverride::class, ['record' => $override->getKey()])
        ->fillForm([
            'type' => 'extra_availability',
            'date' => now()->addDays(7)->toDateString(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('doctor_schedule_overrides', [
        'id' => $override->id,
        'type' => 'extra_availability',
    ]);
});

// ─── Delete ─────────────────────────────────────────────────────────

it('can delete an override', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $override = DoctorScheduleOverride::factory()->for($doctor)->create();

    Livewire::actingAs($doctorUser)
        ->test(EditDoctorScheduleOverride::class, ['record' => $override->getKey()])
        ->callAction('delete')
        ->assertHasNoFormErrors();

    $this->assertDatabaseMissing('doctor_schedule_overrides', [
        'id' => $override->id,
    ]);
});
