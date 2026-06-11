<?php

namespace App\Filament\Resources\ClinicalRecords\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrescriptionItemsRelationManager extends RelationManager
{
    public static function getRelationshipName(): string
    {
        return 'items';
    }

    public function canEdit(mixed $record): bool
    {
        return false;
    }

    public function canDelete(mixed $record): bool
    {
        return auth()->user()?->isDoctor() ?? false;
    }

    public function canCreate(): bool
    {
        return auth()->user()?->isDoctor() ?? false;
    }

    public function canAdd(): bool
    {
        return auth()->user()?->isDoctor() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('position')
                    ->label('#')
                    ->width('4rem')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Medication')
                    ->sortable(),
                TextColumn::make('dosage')
                    ->label('Dosage'),
                TextColumn::make('frequency')
                    ->label('Frequency'),
                TextColumn::make('duration')
                    ->label('Duration'),
            ])
            ->recordActions([])
            ->headerActions([]);
    }
}