<?php

namespace App\Filament\Resources\DoctorScheduleOverrides\Schemas;

use App\Models\Doctor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class DoctorScheduleOverrideForm
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
                DatePicker::make('date')
                    ->required(),
                Select::make('type')
                    ->required()
                    ->options([
                        'block' => 'Block',
                        'extra_availability' => 'Extra Availability',
                    ]),
                TimePicker::make('start_time')
                    ->nullable()
                    ->format('H:i:s'),
                TimePicker::make('end_time')
                    ->nullable()
                    ->format('H:i:s'),
                Textarea::make('reason')
                    ->nullable()
                    ->maxLength(1000),
            ]);
    }
}
