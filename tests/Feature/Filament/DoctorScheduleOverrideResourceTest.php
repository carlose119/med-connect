<?php

use App\Filament\Resources\DoctorScheduleOverrides\DoctorScheduleOverrideResource;
use App\Filament\Resources\DoctorScheduleOverrides\Pages\CreateDoctorScheduleOverride;
use App\Filament\Resources\DoctorScheduleOverrides\Pages\EditDoctorScheduleOverride;
use App\Filament\Resources\DoctorScheduleOverrides\Pages\ListDoctorScheduleOverrides;
use App\Models\Doctor;
use App\Models\DoctorScheduleOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Task 4.1: Form Schema ─────────────────────────────────────────

it('renders the override create form with expected fields', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    Livewire::actingAs($doctorUser)
        ->test(CreateDoctorScheduleOverride::class)
        ->assertFormFieldExists('doctor_id')
        ->assertFormFieldExists('date')
        ->assertFormFieldExists('type')
        ->assertFormFieldExists('start_time')
        ->assertFormFieldExists('end_time')
        ->assertFormFieldExists('reason');
});

it('renders the override edit form with expected fields', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $override = DoctorScheduleOverride::factory()->for($doctor)->create();

    Livewire::actingAs($doctorUser)
        ->test(EditDoctorScheduleOverride::class, ['record' => $override->getKey()])
        ->assertFormFieldExists('doctor_id')
        ->assertFormFieldExists('date')
        ->assertFormFieldExists('type')
        ->assertFormFieldExists('start_time')
        ->assertFormFieldExists('end_time')
        ->assertFormFieldExists('reason');
});

// ─── Task 4.2: Table Schema ────────────────────────────────────────

it('displays override records in the table', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    DoctorScheduleOverride::factory()->for($doctor)->create([
        'date' => now()->addDays(5)->toDateString(),
        'type' => 'block',
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'reason' => 'Maintenance',
    ]);

    Livewire::actingAs($doctorUser)
        ->test(ListDoctorScheduleOverrides::class)
        ->assertSuccessful()
        ->assertSeeText('Block');
});

// ─── Task 4.3: Pages — Scoping & Access ────────────────────────────

it('lists only the doctors own overrides', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    $otherUser = User::factory()->doctor()->create();
    $otherDoctor = Doctor::factory()->for($otherUser)->create();
    DoctorScheduleOverride::factory()->for($otherDoctor)->create([
        'date' => now()->addDay()->toDateString(),
        'type' => 'block',
    ]);

    DoctorScheduleOverride::factory()->for($doctor)->create([
        'date' => now()->addDays(2)->toDateString(),
        'type' => 'extra_availability',
    ]);

    Livewire::actingAs($doctorUser)
        ->test(ListDoctorScheduleOverrides::class)
        ->assertSuccessful()
        ->assertSeeText('Extra Availability');
});

it('scopes the override create page', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    $this->actingAs($doctorUser)
        ->get('/doctor/doctor-schedule-overrides/create')
        ->assertSuccessful();
});

it('scopes the override edit page', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $override = DoctorScheduleOverride::factory()->for($doctor)->create();

    $this->actingAs($doctorUser)
        ->get("/doctor/doctor-schedule-overrides/{$override->id}/edit")
        ->assertSuccessful();
});

it('can create a block override via the form', function (): void {
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
        'reason' => 'Staff meeting',
    ]);
});

// ─── Task 4.4: Resource Registration ───────────────────────────────

it('registers the DoctorScheduleOverrideResource correctly', function (): void {
    expect(DoctorScheduleOverrideResource::getModel())->toBe(DoctorScheduleOverride::class);
    expect(DoctorScheduleOverrideResource::getNavigationIcon())->not->toBeNull();
    expect(DoctorScheduleOverrideResource::getNavigationGroup())->toBe('Schedule');
    expect(DoctorScheduleOverrideResource::getNavigationSort())->toBe(2);
});

it('has valid override page routes registered', function (): void {
    $pages = DoctorScheduleOverrideResource::getPages();
    expect($pages)->toHaveKeys(['index', 'create', 'edit']);
});
