<?php

namespace App\Filament\Resources\AdminAudit\Tables;

use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Admin')
                    ->searchable()
                    ->sortable(),
                BadgeColumn::make('actor_type')
                    ->label('Actor Type')
                    ->colors([
                        'primary' => 'admin',
                        'info' => 'doctor',
                        'secondary' => 'system',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('action')
                    ->label('Action')
                    ->fontFamily('mono')
                    ->sortable(),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->sortable(),
                TextColumn::make('subject_id')
                    ->label('Subject ID')
                    ->alignEnd(),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('actor_type')
                    ->label('Actor Type')
                    ->options([
                        'admin' => 'Admin',
                        'doctor' => 'Doctor',
                        'system' => 'System',
                    ]),
                SelectFilter::make('action')
                    ->label('Action')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'toggled' => 'Toggled',
                        'assigned' => 'Assigned',
                    ]),
                SelectFilter::make('subject_type')
                    ->label('Subject Type')
                    ->options([
                        'Doctor' => 'Doctor',
                        'Patient' => 'Patient',
                        'User' => 'User',
                    ]),
                Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        \Filament\Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query) => $query->whereDate('created_at', '>=', $data['created_from']),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query) => $query->whereDate('created_at', '<=', $data['created_until']),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([])
            ->headerActions([]);
    }
}