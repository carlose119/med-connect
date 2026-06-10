<?php

namespace App\Filament\Resources\ClinicalRecords\Schemas;

use App\Models\Patient;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Creation form for MedicalHistory. Shown when a doctor needs to manually
 * create a history for a patient who was never booked (so
 * EnsureMedicalHistoryAction never fired).
 */
class CreateMedicalHistoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('patient_id')
                    ->label('Paciente')
                    ->searchable()
                    ->preload()
                    ->getSearchResultsUsing(fn (string $search): array => self::searchPatients($search))
                    ->getOptionLabelUsing(fn ($value): ?string => self::getPatientLabel($value))
                    ->required()
                    ->helperText('Solo pacientes sin historial existente se muestran.'),
                Select::make('primary_doctor_id')
                    ->label('Médico a cargo (opcional)')
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => \App\Models\Doctor::query()
                        ->join('users', 'doctors.user_id', '=', 'users.id')
                        ->orderBy('users.name')
                        ->select('doctors.*')
                        ->get()
                        ->mapWithKeys(fn ($doctor): array => [$doctor->id => $doctor->user->name])
                        ->all())
                    ->nullable(),
                TextInput::make('opened_at')
                    ->label('Fecha de apertura')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn (): string => now()->format('Y-m-d H:i:s')),
            ]);
    }

    private static function searchPatients(string $search): array
    {
        return Patient::query()
            ->whereDoesntHave('medicalHistory')
            ->where(function ($q) use ($search): void {
                $q->whereHas('user', fn ($q): Builder => $q->where('name', 'like', "%{$search}%"))
                    ->orWhere('identification_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->with('user')
            ->limit(20)
            ->get()
            ->mapWithKeys(fn ($patient): array => [$patient->id => $patient->user->name])
            ->all();
    }

    private static function getPatientLabel(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        $patient = Patient::with('user')->find($value);

        return $patient?->user->name;
    }
}