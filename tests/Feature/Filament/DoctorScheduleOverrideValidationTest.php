<?php

use App\Filament\Resources\DoctorScheduleOverrides\Pages\CreateDoctorScheduleOverride;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Task 5.5: Nullable times for full-day override ─────────────────

it('accepts a block override with null start_time and end_time (full-day block)', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    Livewire::actingAs($doctorUser)
        ->test(CreateDoctorScheduleOverride::class)
        ->fillForm([
            'date' => now()->addDays(10)->toDateString(),
            'type' => 'block',
            'start_time' => null,
            'end_time' => null,
            'reason' => 'Full-day maintenance',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('doctor_schedule_overrides', [
        'doctor_id' => $doctor->id,
        'type' => 'block',
        'start_time' => null,
        'end_time' => null,
        'reason' => 'Full-day maintenance',
    ]);
});

it('accepts a block override with only start_time set (partial block)', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    Livewire::actingAs($doctorUser)
        ->test(CreateDoctorScheduleOverride::class)
        ->fillForm([
            'date' => now()->addDays(10)->toDateString(),
            'type' => 'block',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'reason' => 'Morning maintenance',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('doctor_schedule_overrides', [
        'doctor_id' => $doctor->id,
        'type' => 'block',
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);
});
