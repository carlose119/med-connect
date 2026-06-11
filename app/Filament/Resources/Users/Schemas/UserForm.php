<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Specialty;
use App\Models\User;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

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
                    ->rule(
                        fn (Get $get): \Illuminate\Validation\Rules\Unique | null => $get('role') === 'doctor'
                            ? null
                            : Rule::unique('users', 'email'),
                    )
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation, Get $get): bool =>
                        $operation === 'create' && $get('role') !== 'doctor'
                    )
                    ->dehydrated(fn (string $operation, Get $get): bool =>
                        $operation === 'create' && $get('role') !== 'doctor'
                    )
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
                    ->visible(fn (Get $get): bool => $get('role') === 'doctor')
                    ->dehydrated(fn (Get $get): bool => $get('role') === 'doctor')
                    ->native(false),
                TextInput::make('license_number')
                    ->label('License number')
                    ->maxLength(255)
                    ->required()
                    ->visible(fn (Get $get): bool => $get('role') === 'doctor')
                    ->dehydrated(fn (Get $get): bool => $get('role') === 'doctor'),
                Checkbox::make('is_super_admin')
                    ->label('Super Admin (Shield)')
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
                Checkbox::make('is_active')
                    ->label('Active (can login)')
                    ->default(true)
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
            ]);
    }
}