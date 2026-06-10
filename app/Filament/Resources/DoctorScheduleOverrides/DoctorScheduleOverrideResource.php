<?php

namespace App\Filament\Resources\DoctorScheduleOverrides;

use App\Filament\Resources\DoctorScheduleOverrides\Pages\CreateDoctorScheduleOverride;
use App\Filament\Resources\DoctorScheduleOverrides\Pages\EditDoctorScheduleOverride;
use App\Filament\Resources\DoctorScheduleOverrides\Pages\ListDoctorScheduleOverrides;
use App\Filament\Resources\DoctorScheduleOverrides\Schemas\DoctorScheduleOverrideForm;
use App\Filament\Resources\DoctorScheduleOverrides\Tables\DoctorScheduleOverridesTable;
use App\Models\DoctorScheduleOverride;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DoctorScheduleOverrideResource extends Resource
{
    protected static ?string $model = DoctorScheduleOverride::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string | \UnitEnum | null $navigationGroup = 'Schedule';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return DoctorScheduleOverrideForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DoctorScheduleOverridesTable::configure($table);
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
            'index' => ListDoctorScheduleOverrides::route('/'),
            'create' => CreateDoctorScheduleOverride::route('/create'),
            'edit' => EditDoctorScheduleOverride::route('/{record}/edit'),
        ];
    }
}
