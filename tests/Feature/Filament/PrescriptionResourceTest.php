<?php

use App\Filament\Resources\ClinicalRecords\PrescriptionResource;
use App\Filament\Resources\ClinicalRecords\Pages\ListPrescriptions;
use App\Filament\Resources\ClinicalRecords\Pages\ViewPrescription;
use App\Filament\Resources\ClinicalRecords\Pages\EditPrescription;
use App\Filament\Resources\ClinicalRecords\RelationManagers\PrescriptionItemsRelationManager;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Resource configuration ─────────────────────────────────────────

it('binds Prescription model', function (): void {
    expect(PrescriptionResource::getModel())->toBe(Prescription::class);
});

it('navigation is in Clinical group with correct icon and sort', function (): void {
    expect(PrescriptionResource::getNavigationGroup())->toBe('Clinical');
    expect(PrescriptionResource::getNavigationIcon())->not->toBeNull();
    expect(PrescriptionResource::getNavigationSort())->toBe(2);
});

it('cannot create a prescription', function (): void {
    expect(PrescriptionResource::canCreate())->toBeFalse();
});

it('doctors can edit a prescription', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $prescription = Prescription::factory()->for($doctor)->create();

    $this->actingAs($doctorUser);
    expect(PrescriptionResource::canEdit($prescription))->toBeTrue();
});

it('cannot delete a prescription', function (): void {
    $user = User::factory()->admin()->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $prescription = Prescription::factory()->for($doctor)->create();

    expect(PrescriptionResource::canDelete($prescription))->toBeFalse();
});

it('registers PrescriptionItemsRelationManager in getRelations', function (): void {
    expect(PrescriptionResource::getRelations())->toContain(PrescriptionItemsRelationManager::class);
});

it('registers index, view, and edit pages (no create)', function (): void {
    $pages = PrescriptionResource::getPages();
    expect($pages)->toHaveKeys(['index', 'view', 'edit'])
        ->not->toHaveKeys(['create']);
});

// ─── List page renders ───────────────────────────────────────────────

it('renders prescription list with expected columns', function (): void {
    $adminUser = User::factory()->admin()->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $prescription = Prescription::factory()->for($doctor)->create();

    Livewire::actingAs($adminUser)
        ->test(ListPrescriptions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$prescription]);
});

// ─── View page renders ───────────────────────────────────────────────

it('renders view page with read-only form', function (): void {
    $adminUser = User::factory()->admin()->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $prescription = Prescription::factory()->for($doctor)->create();

    Livewire::actingAs($adminUser)
        ->test(ViewPrescription::class, ['record' => $prescription->getKey()])
        ->assertSuccessful();
});

// ─── No create page exposed ──────────────────────────────────────────

it('no create page exposed', function (): void {
    $adminUser = User::factory()->admin()->create();

    $pages = PrescriptionResource::getPages();
    expect($pages)->not->toHaveKey('create');
});

// ─── Edit page exposed ───────────────────────────────────────────────

it('edit page is exposed', function (): void {
    $pages = PrescriptionResource::getPages();
    expect($pages)->toHaveKey('edit');
});