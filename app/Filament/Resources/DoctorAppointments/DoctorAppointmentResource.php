<?php

namespace App\Filament\Resources\DoctorAppointments;

use App\Filament\Resources\DoctorAppointments\Pages\ListDoctorAppointments;
use App\Filament\Resources\DoctorAppointments\Tables\DoctorAppointmentsTable;
use App\Models\Appointment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DoctorAppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static ?string $slug = 'appointments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function table(Table $table): Table
    {
        return DoctorAppointmentsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'doctor';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('doctor.user', fn (Builder $q): Builder => $q->where('id', auth()->id()))
            ->with(['patient.user']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDoctorAppointments::route('/'),
        ];
    }
}
