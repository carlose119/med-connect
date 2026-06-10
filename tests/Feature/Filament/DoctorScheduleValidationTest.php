<?php

use App\Filament\Resources\DoctorSchedules\Pages\CreateDoctorSchedule;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Task 5.3: Non-positive duration rejected ───────────────────────

it('rejects slot_duration_minutes of 0 via the form', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    Doctor::factory()->for($doctorUser)->create();

    Livewire::actingAs($doctorUser)
        ->test(CreateDoctorSchedule::class)
        ->fillForm([
            'day_of_week' => 2,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_duration_minutes' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['slot_duration_minutes']);
});

it('rejects negative slot_duration_minutes via the form', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    Doctor::factory()->for($doctorUser)->create();

    Livewire::actingAs($doctorUser)
        ->test(CreateDoctorSchedule::class)
        ->fillForm([
            'day_of_week' => 2,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_duration_minutes' => -10,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['slot_duration_minutes']);
});

// ─── Task 5.4: End before start rejected ────────────────────────────

it('rejects an end time that is before the start time', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    Doctor::factory()->for($doctorUser)->create();

    Livewire::actingAs($doctorUser)
        ->test(CreateDoctorSchedule::class)
        ->fillForm([
            'day_of_week' => 2,
            'start_time' => '17:00:00',
            'end_time' => '09:00:00',
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['end_time']);
});

it('rejects an end time that equals the start time', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    Doctor::factory()->for($doctorUser)->create();

    Livewire::actingAs($doctorUser)
        ->test(CreateDoctorSchedule::class)
        ->fillForm([
            'day_of_week' => 2,
            'start_time' => '09:00:00',
            'end_time' => '09:00:00',
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['end_time']);
});
