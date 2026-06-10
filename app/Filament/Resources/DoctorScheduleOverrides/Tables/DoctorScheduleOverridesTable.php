<?php

namespace App\Filament\Resources\DoctorScheduleOverrides\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DoctorScheduleOverridesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'block' => 'danger',
                        'extra_availability' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'block' => 'Block',
                        'extra_availability' => 'Extra Availability',
                        default => $state,
                    }),
                TextColumn::make('start_time')
                    ->time('H:i')
                    ->placeholder('—'),
                TextColumn::make('end_time')
                    ->time('H:i')
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->limit(50)
                    ->tooltip(fn (string $state): ?string => strlen($state) > 50 ? $state : null),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'block' => 'Block',
                        'extra_availability' => 'Extra Availability',
                    ]),
            ])
            ->defaultSort('date', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
