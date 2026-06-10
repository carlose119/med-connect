<?php

use App\Filament\Resources\ClinicalRecords\MedicalHistoryResource;
use App\Filament\Resources\ClinicalRecords\Pages\ListMedicalHistories;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('doctor sees only histories of patients they have appointments with', function (): void {
    // Doctor A has an appointment with Patient A
    $doctorAUser = User::factory()->doctor()->create();
    $doctorA = Doctor::factory()->for($doctorAUser)->create();
    $patientAUser = User::factory()->patient()->create();
    $patientA = Patient::factory()->for($patientAUser)->create();
    $historyA = MedicalHistory::factory()->for($patientA)->for($doctorA, 'primaryDoctor')->create();
    Appointment::factory()->for($doctorA)->for($patientA)->create(['state' => 'completed']);

    // Doctor A does NOT have an appointment with Patient B
    $doctorBUser = User::factory()->doctor()->create();
    $doctorB = Doctor::factory()->for($doctorBUser)->create();
    $patientBUser = User::factory()->patient()->create();
    $patientB = Patient::factory()->for($patientBUser)->create();
    $historyB = MedicalHistory::factory()->for($patientB)->for($doctorB, 'primaryDoctor')->create();
    Appointment::factory()->for($doctorB)->for($patientB)->create(['state' => 'completed']);

    Livewire::actingAs($doctorAUser)
        ->test(ListMedicalHistories::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$historyA])
        ->assertCanNotSeeTableRecords([$historyB]);
});

it('admin sees all histories', function (): void {
    $adminUser = User::factory()->admin()->create();

    $doctorAUser = User::factory()->doctor()->create();
    $doctorA = Doctor::factory()->for($doctorAUser)->create();
    $patientAUser = User::factory()->patient()->create();
    $patientA = Patient::factory()->for($patientAUser)->create();
    $historyA = MedicalHistory::factory()->for($patientA)->for($doctorA, 'primaryDoctor')->create();

    $doctorBUser = User::factory()->doctor()->create();
    $doctorB = Doctor::factory()->for($doctorBUser)->create();
    $patientBUser = User::factory()->patient()->create();
    $patientB = Patient::factory()->for($patientBUser)->create();
    $historyB = MedicalHistory::factory()->for($patientB)->for($doctorB, 'primaryDoctor')->create();

    Livewire::actingAs($adminUser)
        ->test(ListMedicalHistories::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$historyA, $historyB]);
});

it('doctor cannot access another doctors patient history via direct URL', function (): void {
    // Doctor B has a patient
    $doctorBUser = User::factory()->doctor()->create();
    $doctorB = Doctor::factory()->for($doctorBUser)->create();
    $patientBUser = User::factory()->patient()->create();
    $patientB = Patient::factory()->for($patientBUser)->create();
    $historyB = MedicalHistory::factory()->for($patientB)->for($doctorB, 'primaryDoctor')->create();
    // No appointment between Doctor B and this patient — no relation

    // Doctor A tries to access Doctor B's patient history
    $doctorAUser = User::factory()->doctor()->create();
    $doctorA = Doctor::factory()->for($doctorAUser)->create();

    $this->actingAs($doctorAUser)
        ->get("/doctor/clinical-records/medical-histories/{$historyB->id}/edit")
        ->assertStatus(404);
});