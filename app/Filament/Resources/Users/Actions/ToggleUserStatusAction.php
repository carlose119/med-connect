<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;

class ToggleUserStatusAction
{
    public static function make(): Action
    {
        return Action::make('toggleStatus')
            ->label(fn (User $record): string => $record->isActive() ? 'Suspender' : 'Activar')
            ->icon(fn (User $record): string => $record->isActive()
                ? 'heroicon-o-no-symbol'
                : 'heroicon-o-check-circle')
            ->color(fn (User $record): string => $record->isActive() ? 'warning' : 'success')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => $record->isActive()
                ? 'Suspender usuario'
                : 'Activar usuario')
            ->modalDescription(fn (User $record): string => $record->isActive()
                ? "¿Estás seguro de que deseas suspender a ** {$record->name}**? No podrá iniciar sesión."
                : "¿Estás seguro de que deseas activar a ** {$record->name}**? Podrá iniciar sesión nuevamente.")
            ->action(function (User $record): void {
                DB::transaction(function () use ($record): void {
                    $record->is_active = $record->isActive() ? false : true;
                    $record->save();
                });
            })
            ->successNotificationTitle(fn (User $record): string => $record->isActive()
                ? "{$record->name} ha sido activado"
                : "{$record->name} ha sido suspendido");
    }
}