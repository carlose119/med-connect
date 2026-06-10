<?php

namespace App\Filament\Resources\DoctorScheduleOverrides\Schemas;

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
        return $schema
            ->components([
                Hidden::make('doctor_id')
                    ->default(fn () => auth()->user()?->doctor?->id),
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
