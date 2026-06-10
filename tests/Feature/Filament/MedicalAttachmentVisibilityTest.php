<?php

use App\Filament\Resources\ClinicalRecords\Pages\EditMedicalHistory;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalAttachment;
use App\Models\MedicalHistory;
use App\Models\MedicalNote;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('note view shows attachment rows when attachments exist', function (): void {
    Storage::fake('clinical_attachments');

    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();
    $appointment = Appointment::factory()->for($doctor)->for($patient)->completed()->create();
    $note = MedicalNote::factory()->for($history)->for($doctor)->for($appointment)->create();

    MedicalAttachment::factory()->for($note)->create([
        'file_name' => 'lab_results.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 204800, // 200 KB
    ]);

    Livewire::actingAs($doctorUser)
        ->test(EditMedicalHistory::class, ['record' => $history->getKey()])
        ->assertSuccessful();
});