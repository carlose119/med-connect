<?php

use App\Filament\Resources\ClinicalRecords\PrescriptionResource;
use App\Filament\Resources\ClinicalRecords\Pages\ListPrescriptions;
use App\Filament\Resources\ClinicalRecords\Pages\ViewPrescription;
use App\Models\Doctor;
use App\Models\Prescription;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('doctor sees only own prescriptions', function (): void {
    // Doctor A's prescription
    $doctorAUser = User::factory()->doctor()->create();
    $doctorA = Doctor::factory()->for($doctorAUser)->create();
    $patientAUser = User::factory()->patient()->create();
    $patientA = Patient::factory()->for($patientAUser)->create();
    $prescriptionA = Prescription::factory()->for($doctorA)->for($patientA)->create();

    // Doctor B's prescription
    $doctorBUser = User::factory()->doctor()->create();
    $doctorB = Doctor::factory()->for($doctorBUser)->create();
    $patientBUser = User::factory()->patient()->create();
    $patientB = Patient::factory()->for($patientBUser)->create();
    $prescriptionB = Prescription::factory()->for($doctorB)->for($patientB)->create();

    Livewire::actingAs($doctorAUser)
        ->test(ListPrescriptions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$prescriptionA])
        ->assertCanNotSeeTableRecords([$prescriptionB]);
});

it('admin sees all prescriptions', function (): void {
    $adminUser = User::factory()->admin()->create();

    $doctorAUser = User::factory()->doctor()->create();
    $doctorA = Doctor::factory()->for($doctorAUser)->create();
    $patientAUser = User::factory()->patient()->create();
    $patientA = Patient::factory()->for($patientAUser)->create();
    $prescriptionA = Prescription::factory()->for($doctorA)->for($patientA)->create();

    $doctorBUser = User::factory()->doctor()->create();
    $doctorB = Doctor::factory()->for($doctorBUser)->create();
    $patientBUser = User::factory()->patient()->create();
    $patientB = Patient::factory()->for($patientBUser)->create();
    $prescriptionB = Prescription::factory()->for($doctorB)->for($patientB)->create();

    Livewire::actingAs($adminUser)
        ->test(ListPrescriptions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$prescriptionA, $prescriptionB]);
});