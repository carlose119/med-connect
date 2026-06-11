<?php

use App\Filament\Resources\ClinicalRecords\MedicalHistoryResource;
use App\Filament\Resources\ClinicalRecords\Pages\CreateMedicalHistory;
use App\Filament\Resources\ClinicalRecords\RelationManagers\MedicalNotesRelationManager;
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

it('doctors and admins can create a medical history', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $adminUser = User::factory()->admin()->create();

    // Doctor can create
    $this->actingAs($doctorUser);
    expect(MedicalHistoryResource::canCreate())->toBeTrue();

    // Admin can create
    $this->actingAs($adminUser);
    expect(MedicalHistoryResource::canCreate())->toBeTrue();
});

it('patients cannot create a medical history', function (): void {
    $patientUser = User::factory()->patient()->create();

    $this->actingAs($patientUser);
    expect(MedicalHistoryResource::canCreate())->toBeFalse();
});

it('doctors can edit a medical history', function (): void {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();

    $this->actingAs($doctorUser);
    expect(MedicalHistoryResource::canEdit($history))->toBeTrue();
});

it('patients cannot edit a medical history', function (): void {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();

    $this->actingAs($patientUser);
    expect(MedicalHistoryResource::canEdit($history))->toBeFalse();
});

it('cannot delete a medical history', function (): void {
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

it('registers index, create, view, and edit pages', function (): void {
    $pages = MedicalHistoryResource::getPages();
    expect($pages)->toHaveKeys(['index', 'create', 'view', 'edit']);
});

it('edit form class exposes patient_name field', function (): void {
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

it('edit form class exposes primary_doctor_id field', function (): void {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();
    \App\Models\Appointment::factory()->for($doctor)->for($patient)->create(['state' => 'completed']);

    Livewire::actingAs($doctorUser)
        ->test(\App\Filament\Resources\ClinicalRecords\Pages\EditMedicalHistory::class, ['record' => $history->getKey()])
        ->assertFormFieldExists('primary_doctor_id');
});

it('edit form class exposes opened_at field', function (): void {
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

it('edit form patient_name and opened_at are disabled, primary_doctor_id is editable', function (): void {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();
    \App\Models\Appointment::factory()->for($doctor)->for($patient)->create(['state' => 'completed']);

    Livewire::actingAs($doctorUser)
        ->test(\App\Filament\Resources\ClinicalRecords\Pages\EditMedicalHistory::class, ['record' => $history->getKey()])
        ->assertFormFieldDisabled('patient_name')
        ->assertFormFieldDisabled('opened_at')
        ->assertFormFieldEnabled('primary_doctor_id');
});

it('create page renders with create form', function (): void {
    $doctorUser = User::factory()->doctor()->create();

    Livewire::actingAs($doctorUser)
        ->test(CreateMedicalHistory::class)
        ->assertFormFieldExists('patient_id');
});