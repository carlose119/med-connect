<?php

use App\Filament\Resources\ClinicalRecords\MedicalHistoryResource;
use App\Filament\Resources\ClinicalRecords\RelationManagers\MedicalNotesRelationManager;
use App\Filament\Resources\ClinicalRecords\Schemas\MedicalHistoryForm;
use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('binds MedicalHistory model', function (): void {
    expect(MedicalHistoryResource::getModel())->toBe(MedicalHistory::class);
});

it('navigation is in Clinical group with correct icon and sort', function (): void {
    expect(MedicalHistoryResource::getNavigationGroup())->toBe('Clinical');
    expect(MedicalHistoryResource::getNavigationIcon())->not->toBeNull();
    expect(MedicalHistoryResource::getNavigationSort())->toBe(1);
});

it('cannot create a medical history', function (): void {
    expect(MedicalHistoryResource::canCreate())->toBeFalse();
});

it('cannot edit a medical history', function (): void {
    $user = User::factory()->admin()->create();
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();

    expect(MedicalHistoryResource::canEdit($history))->toBeFalse();
});

it('cannot delete a medical history', function (): void {
    $user = User::factory()->admin()->create();
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();

    expect(MedicalHistoryResource::canDelete($history))->toBeFalse();
});

it('registers MedicalNotesRelationManager in getRelations', function (): void {
    expect(MedicalHistoryResource::getRelations())->toContain(MedicalNotesRelationManager::class);
});

it('registers only index and edit pages', function (): void {
    $pages = MedicalHistoryResource::getPages();
    expect($pages)->toHaveKeys(['index', 'edit'])
        ->not->toHaveKeys(['create']);
});

it('form class exposes patient_name field', function (): void {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();
    \App\Models\Appointment::factory()->for($doctor)->for($patient)->create(['state' => 'completed']);

    Livewire::actingAs($doctorUser)
        ->test(\App\Filament\Resources\ClinicalRecords\Pages\EditMedicalHistory::class, ['record' => $history->getKey()])
        ->assertFormFieldExists('patient_name');
});

it('form class exposes primary_doctor_name field', function (): void {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();
    \App\Models\Appointment::factory()->for($doctor)->for($patient)->create(['state' => 'completed']);

    Livewire::actingAs($doctorUser)
        ->test(\App\Filament\Resources\ClinicalRecords\Pages\EditMedicalHistory::class, ['record' => $history->getKey()])
        ->assertFormFieldExists('primary_doctor_name');
});

it('form class exposes opened_at field', function (): void {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();
    \App\Models\Appointment::factory()->for($doctor)->for($patient)->create(['state' => 'completed']);

    Livewire::actingAs($doctorUser)
        ->test(\App\Filament\Resources\ClinicalRecords\Pages\EditMedicalHistory::class, ['record' => $history->getKey()])
        ->assertFormFieldExists('opened_at');
});

it('form fields are disabled', function (): void {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();
    \App\Models\Appointment::factory()->for($doctor)->for($patient)->create(['state' => 'completed']);

    Livewire::actingAs($doctorUser)
        ->test(\App\Filament\Resources\ClinicalRecords\Pages\EditMedicalHistory::class, ['record' => $history->getKey()])
        ->assertFormFieldDisabled('patient_name')
        ->assertFormFieldDisabled('primary_doctor_name')
        ->assertFormFieldDisabled('opened_at');
});