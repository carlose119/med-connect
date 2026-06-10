<?php

namespace App\Filament\Resources\ClinicalRecords\Schemas;

use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MedicalHistoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make('Patient Information')
                    ->schema([
                        TextInput::make('patient_name')
                            ->label('Patient')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->patient?->user?->name ?? '—'),
                        TextInput::make('primary_doctor_name')
                            ->label('Primary Doctor')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->primaryDoctor?->user?->name ?? '—'),
                        TextInput::make('opened_at')
                            ->label('Opened At')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->opened_at?->format('Y-m-d H:i:s') ?? '—'),
                    ]),
            ]);
    }
}