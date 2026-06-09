<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Specialty;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create')
                    ->minLength(8)
                    ->maxLength(255),
                Select::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'doctor' => 'Doctor',
                        'patient' => 'Patient',
                    ])
                    ->default('patient')
                    ->required()
                    ->live()
                    ->native(false),
                Select::make('specialty_id')
                    ->label('Specialty')
                    ->options(fn () => Specialty::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('role') === 'doctor')
                    ->dehydrated(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('role') === 'doctor')
                    ->native(false),
                TextInput::make('license_number')
                    ->label('License number')
                    ->maxLength(255)
                    ->required()
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('role') === 'doctor')
                    ->dehydrated(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('role') === 'doctor'),
                Checkbox::make('is_super_admin')
                    ->label('Super Admin (Shield)')
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
            ]);
    }
}
