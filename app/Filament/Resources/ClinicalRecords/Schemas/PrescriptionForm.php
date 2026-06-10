<?php

namespace App\Filament\Resources\ClinicalRecords\Schemas;

use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PrescriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make('Prescription Details')
                    ->schema([
                        TextInput::make('patient_name')
                            ->label('Patient')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->patient?->user?->name ?? '—'),
                        TextInput::make('doctor_name')
                            ->label('Doctor')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->doctor?->user?->name ?? '—'),
                        TextInput::make('unique_code')
                            ->label('Unique Code')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->unique_code ?? '—'),
                        TextInput::make('issued_at')
                            ->label('Issued At')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->issued_at?->format('Y-m-d H:i') ?? '—'),
                        TextInput::make('status')
                            ->label('Status')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => ucfirst($record?->status ?? '—')),
                        TextInput::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record?->status === 'cancelled')
                            ->formatStateUsing(fn ($record) => $record?->cancellation_reason ?? '—'),
                        TextInput::make('items_count')
                            ->label('Items')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => (string) ($record?->items()->count() ?? 0)),
                    ]),
            ]);
    }
}