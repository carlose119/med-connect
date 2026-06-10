<?php

use App\Filament\Resources\DoctorSchedules\DoctorScheduleResource;
use App\Filament\Resources\DoctorSchedules\Pages\CreateDoctorSchedule;
use App\Filament\Resources\DoctorSchedules\Pages\EditDoctorSchedule;
use App\Filament\Resources\DoctorSchedules\Pages\ListDoctorSchedules;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Task 3.1: Form Schema ─────────────────────────────────────────

it('renders the create form with expected fields', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    Doctor::factory()->for($doctorUser)->create();

    Livewire::actingAs($doctorUser)
        ->test(CreateDoctorSchedule::class)
        ->assertFormFieldExists('doctor_id')
        ->assertFormFieldExists('day_of_week')
        ->assertFormFieldExists('start_time')
        ->assertFormFieldExists('end_time')
        ->assertFormFieldExists('slot_duration_minutes')
        ->assertFormFieldExists('is_active');
});

it('renders the edit form with expected fields', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $schedule = DoctorSchedule::factory()->for($doctor)->create();

    Livewire::actingAs($doctorUser)
        ->test(EditDoctorSchedule::class, ['record' => $schedule->getKey()])
        ->assertFormFieldExists('doctor_id')
        ->assertFormFieldExists('day_of_week')
        ->assertFormFieldExists('start_time')
        ->assertFormFieldExists('end_time')
        ->assertFormFieldExists('slot_duration_minutes')
        ->assertFormFieldExists('is_active');
});

// ─── Task 3.2: Table Schema ────────────────────────────────────────

it('displays schedule records in the table', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    DoctorSchedule::factory()->for($doctor)->create([
        'day_of_week' => 2, // Tuesday
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'slot_duration_minutes' => 30,
        'is_active' => true,
    ]);

    Livewire::actingAs($doctorUser)
        ->test(ListDoctorSchedules::class)
        ->assertSuccessful()
        ->assertSeeText('Tuesday')
        ->assertSeeText('09:00')
        ->assertSeeText('17:00');
});

// ─── Task 3.3: Pages — Scoping & Access ────────────────────────────

it('lists only the doctors own schedules', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    // Another doctor's schedule — should NOT appear.
    $otherUser = User::factory()->doctor()->create();
    $otherDoctor = Doctor::factory()->for($otherUser)->create();
    $otherSchedule = DoctorSchedule::factory()->for($otherDoctor)->create([
        'day_of_week' => 1,
    ]);

    // Own schedule — SHOULD appear.
    $ownSchedule = DoctorSchedule::factory()->for($doctor)->create([
        'day_of_week' => 2,
    ]);

    Livewire::actingAs($doctorUser)
        ->test(ListDoctorSchedules::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(collect([$ownSchedule]))
        ->assertCanNotSeeTableRecords(collect([$otherSchedule]));
});

it('scopes the create page to the current doctor', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    $this->actingAs($doctorUser)
        ->get('/doctor/doctor-schedules/create')
        ->assertSuccessful();
});

it('scopes the edit page to the current doctor', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $schedule = DoctorSchedule::factory()->for($doctor)->create();

    $this->actingAs($doctorUser)
        ->get("/doctor/doctor-schedules/{$schedule->id}/edit")
        ->assertSuccessful();
});

it('can create a schedule record via the form', function (): void {
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

// ─── Task 3.4: Resource Registration ───────────────────────────────

it('registers the DoctorScheduleResource correctly', function (): void {
    expect(DoctorScheduleResource::getModel())->toBe(DoctorSchedule::class);
    expect(DoctorScheduleResource::getNavigationIcon())->not->toBeNull();
    expect(DoctorScheduleResource::getNavigationGroup())->toBe('Schedule');
    expect(DoctorScheduleResource::getNavigationSort())->toBe(1);
});

it('has valid page routes registered', function (): void {
    $pages = DoctorScheduleResource::getPages();
    expect($pages)->toHaveKeys(['index', 'create', 'edit']);
});
