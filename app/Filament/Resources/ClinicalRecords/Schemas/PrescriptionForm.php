<?php

namespace App\Filament\Resources\ClinicalRecords\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PrescriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        $statusOptions = ['active' => 'Active', 'cancelled' => 'Cancelled'];

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
                        Select::make('status')
                            ->label('Status')
                            ->options($statusOptions)
                            ->visible(fn ($record) => $record !== null),
                        Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->visible(fn ($record) => $record?->status === 'cancelled')
                            ->rows(2),
                        TextInput::make('items_count')
                            ->label('Items')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => (string) ($record?->items()->count() ?? 0)),
                    ]),
            ]);
    }
}