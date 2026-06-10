<?php

namespace App\Filament\Resources\ClinicalRecords\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MedicalHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('patient.user.name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('opened_at')
                    ->label('Opened At')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('notes_count')
                    ->label('Notes')
                    ->badge()
                    ->counts('notes'),
                TextColumn::make('primaryDoctor.user.name')
                    ->label('Primary Doctor')
                    ->sortable(),
            ])
            ->recordActions([])
            ->headerActions([]);
    }
}