<?php

namespace App\Filament\Resources\ClinicalRecords\Pages;

use App\Filament\Resources\ClinicalRecords\MedicalHistoryResource;
use App\Filament\Resources\ClinicalRecords\Schemas\CreateMedicalHistoryForm;
use App\Models\MedicalHistory;
use App\Models\Patient;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateMedicalHistory extends CreateRecord
{
    protected static string $resource = MedicalHistoryResource::class;

    public function form(Schema $schema): Schema
    {
        return CreateMedicalHistoryForm::configure($schema);
    }

    public function mount(): void
    {
        parent::mount();

        // Only doctors can create medical histories via this page.
        // Non-doctors are redirected by canCreate() gate.
        abort_unless(auth()->user()?->isDoctor(), 403);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $patientId = (int) $data['patient_id'];

        // Guard against duplicate: unique index on patient_id prevents
        // simultaneous creations, but we also check proactively.
        if (MedicalHistory::where('patient_id', $patientId)->exists()) {
            $this->notify(
                type: 'warning',
                title: 'El paciente ya tiene un historial clínico.',
                body: 'No es necesario crear uno nuevo.',
            );
            $this->redirect($this->getResource()::getUrl('index'));

            return MedicalHistory::where('patient_id', $patientId)->firstOrFail();
        }

        return DB::transaction(function () use ($patientId, $data): MedicalHistory {
            return MedicalHistory::create([
                'patient_id' => $patientId,
                'primary_doctor_id' => $data['primary_doctor_id'] ?? null,
                'opened_at' => now(),
            ]);
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Historial clínico creado correctamente.';
    }
}