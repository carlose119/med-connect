<?php

use App\Filament\Resources\ClinicalRecords\Pages\ListMedicalHistories;
use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the list page successfully', function (): void {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();
    \App\Models\Appointment::factory()->for($doctor)->for($patient)->create(['state' => 'completed']);

    Livewire::actingAs($doctorUser)
        ->test(ListMedicalHistories::class)
        ->assertSuccessful();
});

it('list page shows patient name in the table', function (): void {
    $patientUser = User::factory()->patient()->create(['name' => 'John Doe']);
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();
    \App\Models\Appointment::factory()->for($doctor)->for($patient)->create(['state' => 'completed']);

    Livewire::actingAs($doctorUser)
        ->test(ListMedicalHistories::class)
        ->assertSuccessful()
        ->assertSeeText('John Doe');
});

it('list page shows opened_at in the table', function (): void {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create(['opened_at' => now()]);
    \App\Models\Appointment::factory()->for($doctor)->for($patient)->create(['state' => 'completed']);

    Livewire::actingAs($doctorUser)
        ->test(ListMedicalHistories::class)
        ->assertSuccessful()
        ->assertSeeText($history->opened_at->format('Y-m-d'));
});

it('list page has no record action buttons', function (): void {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();
    \App\Models\Appointment::factory()->for($doctor)->for($patient)->create(['state' => 'completed']);

    $component = Livewire::actingAs($doctorUser)
        ->test(ListMedicalHistories::class)
        ->assertSuccessful();

    // Assert no Edit button is rendered in the table
    $component->assertDontSeeHtml('wire\\:click=\"table\\.record\\.actions\\.');

    // Also assert record actions are empty at the table level
    expect($component->instance()->getTable()->getRecordActions())->toBeEmpty();
});