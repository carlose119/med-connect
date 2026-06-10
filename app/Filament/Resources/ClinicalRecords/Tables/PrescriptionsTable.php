<?php

namespace App\Filament\Resources\ClinicalRecords\Tables;

use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PrescriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('patient.user.name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('doctor.user.name')
                    ->label('Doctor')
                    ->sortable(),
                TextColumn::make('unique_code')
                    ->label('Code')
                    ->fontFamily('mono'),
                TextColumn::make('issued_at')
                    ->label('Issued')
                    ->date('Y-m-d')
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->items()->count()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'cancelled' => 'Cancelled',
                    ]),
                Filter::make('issued_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('issued_from')
                            ->label('Issued From'),
                        \Filament\Forms\Components\DatePicker::make('issued_until')
                            ->label('Issued Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['issued_from'],
                                fn (Builder $query) => $query->whereDate('issued_at', '>=', $data['issued_from']),
                            )
                            ->when(
                                $data['issued_until'],
                                fn (Builder $query) => $query->whereDate('issued_at', '<=', $data['issued_until']),
                            );
                    }),
            ])
            ->recordActions([])
            ->headerActions([]);
    }
}