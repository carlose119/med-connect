<?php

use App\Filament\Resources\DoctorSchedules\Pages\CreateDoctorSchedule;
use App\Filament\Resources\DoctorSchedules\Pages\EditDoctorSchedule;
use App\Filament\Resources\DoctorSchedules\Pages\ListDoctorSchedules;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Task 5.6: Schedule CRUD ───────────────────────────────────────

// ─── List: doctor scoping ───────────────────────────────────────────

it('lists only the doctors own schedules on the list page', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    $otherUser = User::factory()->doctor()->create();
    $otherDoctor = Doctor::factory()->for($otherUser)->create();
    $otherSchedule = DoctorSchedule::factory()->for($otherDoctor)->create([
        'day_of_week' => 1,
    ]);

    $ownSchedule = DoctorSchedule::factory()->for($doctor)->create([
        'day_of_week' => 2,
    ]);

    Livewire::actingAs($doctorUser)
        ->test(ListDoctorSchedules::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(collect([$ownSchedule]))
        ->assertCanNotSeeTableRecords(collect([$otherSchedule]));
});

// ─── Create ─────────────────────────────────────────────────────────

it('can create a schedule and auto-assigns doctor_id', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    Livewire::actingAs($doctorUser)
        ->test(CreateDoctorSchedule::class)
        ->fillForm([
            'day_of_week' => 3,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('doctor_schedules', [
        'doctor_id' => $doctor->id,
        'day_of_week' => 3,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'slot_duration_minutes' => 30,
        'is_active' => true,
    ]);
});

// ─── Edit: change day_of_week ───────────────────────────────────────

it('can update the day_of_week of a schedule', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $schedule = DoctorSchedule::factory()->for($doctor)->create([
        'day_of_week' => 2,
    ]);

    Livewire::actingAs($doctorUser)
        ->test(EditDoctorSchedule::class, ['record' => $schedule->getKey()])
        ->fillForm([
            'day_of_week' => 4,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('doctor_schedules', [
        'id' => $schedule->id,
        'day_of_week' => 4,
    ]);
});

// ─── Toggle active ──────────────────────────────────────────────────

it('can toggle the is_active flag of a schedule', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $schedule = DoctorSchedule::factory()->for($doctor)->create([
        'is_active' => true,
    ]);

    Livewire::actingAs($doctorUser)
        ->test(EditDoctorSchedule::class, ['record' => $schedule->getKey()])
        ->fillForm([
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('doctor_schedules', [
        'id' => $schedule->id,
        'is_active' => false,
    ]);
});

// ─── Delete ─────────────────────────────────────────────────────────

it('can delete a schedule', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $schedule = DoctorSchedule::factory()->for($doctor)->create();

    Livewire::actingAs($doctorUser)
        ->test(EditDoctorSchedule::class, ['record' => $schedule->getKey()])
        ->callAction('delete')
        ->assertHasNoFormErrors();

    $this->assertDatabaseMissing('doctor_schedules', [
        'id' => $schedule->id,
    ]);
});
