<?php

namespace App\Filament\Resources\DoctorSchedules;

use App\Filament\Resources\DoctorSchedules\Pages\CreateDoctorSchedule;
use App\Filament\Resources\DoctorSchedules\Pages\EditDoctorSchedule;
use App\Filament\Resources\DoctorSchedules\Pages\ListDoctorSchedules;
use App\Filament\Resources\DoctorSchedules\Schemas\DoctorScheduleForm;
use App\Filament\Resources\DoctorSchedules\Tables\DoctorSchedulesTable;
use App\Models\DoctorSchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DoctorScheduleResource extends Resource
{
    protected static ?string $model = DoctorSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string | \UnitEnum | null $navigationGroup = 'Schedule';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return DoctorScheduleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DoctorSchedulesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(
                auth()->user()?->isDoctor(),
                fn (Builder $q) => $q->where('doctor_id', auth()->user()->doctor->id),
            );
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDoctorSchedules::route('/'),
            'create' => CreateDoctorSchedule::route('/create'),
            'edit' => EditDoctorSchedule::route('/{record}/edit'),
        ];
    }
}
