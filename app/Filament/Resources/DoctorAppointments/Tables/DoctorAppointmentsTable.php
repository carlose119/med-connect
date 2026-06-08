<?php

namespace App\Filament\Resources\DoctorAppointments\Tables;

use App\Models\Appointment;
use App\States\Appointment\AppointmentState;
use App\States\Appointment\Cancelled;
use App\States\Appointment\Completed;
use App\States\Appointment\Confirmed;
use App\States\Appointment\NoShow;
use App\States\Appointment\Pending;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DoctorAppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('patient.user.name')
                    ->label('Patient')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('start_time')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Time')
                    ->formatStateUsing(fn (Appointment $record): string => sprintf(
                        '%s–%s',
                        $record->start_time->format('H:i'),
                        $record->end_time->format('H:i'),
                    )),
                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (Appointment $record): string => $record->state::$name)
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'no_show' => 'No Show',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'no_show' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('start_time', 'asc')
            ->filters([
                SelectFilter::make('date_range')
                    ->label('Date Range')
                    ->options([
                        'today' => 'Today',
                        'upcoming' => 'Upcoming',
                        'past' => 'Past',
                    ])
                    ->default('upcoming')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? 'upcoming') {
                        'today' => $query->whereDate('start_time', now()),
                        'upcoming' => $query->where('start_time', '>=', now()),
                        'past' => $query->where('start_time', '<', now()),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Confirm')
                    ->visible(fn (Appointment $record): bool => $record->state instanceof Pending)
                    ->action(fn (Appointment $record): mixed => $record->state->transitionTo(
                        Confirmed::class,
                        auth()->user(),
                    )),
                Action::make('complete')
                    ->label('Complete')
                    ->visible(fn (Appointment $record): bool => $record->state instanceof Confirmed)
                    ->action(fn (Appointment $record): mixed => $record->state->transitionTo(
                        Completed::class,
                        auth()->user(),
                    )),
                Action::make('cancel')
                    ->label('Cancel')
                    ->visible(fn (Appointment $record): bool =>
                        ! $record->state instanceof Completed
                        && ! $record->state instanceof Cancelled
                        && ! $record->state instanceof NoShow
                    )
                    ->form([
                        \Filament\Forms\Components\TextInput::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (Appointment $record, array $data): void {
                        $record->cancellation_reason = $data['cancellation_reason'];
                        $record->state->transitionTo(Cancelled::class, auth()->user());
                    }),
                Action::make('no_show')
                    ->label('No Show')
                    ->visible(fn (Appointment $record): bool => $record->state instanceof Confirmed)
                    ->action(fn (Appointment $record): mixed => $record->state->transitionTo(
                        NoShow::class,
                        auth()->user(),
                    )),
            ])
            ->toolbarActions([]);
    }
}
