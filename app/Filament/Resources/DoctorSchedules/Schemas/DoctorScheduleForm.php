<?php

namespace App\Filament\Resources\DoctorSchedules\Schemas;

use App\Models\Doctor;
use App\Rules\ScheduleDurationPositive;
use App\Rules\ScheduleEndAfterStart;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DoctorScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        $isAdmin = auth()->user()?->isAdmin();

        return $schema
            ->components([
                // Admins pick the doctor; doctors get auto-assigned via mutateFormDataBeforeCreate
                $isAdmin
                    ? Select::make('doctor_id')
                        ->label('Doctor')
                        ->required()
                        ->options(Doctor::query()->with('user')->get()->mapWithKeys(
                            fn (Doctor $d) => [$d->id => $d->user->name],
                        ))
                    : Hidden::make('doctor_id'),
                Select::make('day_of_week')
                    ->required()
                    ->options([
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday',
                        7 => 'Sunday',
                    ]),
                TimePicker::make('start_time')
                    ->required()
                    ->format('H:i:s'),
                TimePicker::make('end_time')
                    ->required()
                    ->format('H:i:s')
                    ->rules([
                        fn ($get) => new ScheduleEndAfterStart($get('start_time')),
                    ]),
                TextInput::make('slot_duration_minutes')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->suffix('min')
                    ->rules([new ScheduleDurationPositive]),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
