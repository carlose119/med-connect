<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Role name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('guard_name')
                    ->label('Guard name')
                    ->required()
                    ->maxLength(255)
                    ->default(config('auth.defaults.guard', 'web')),
                CheckboxList::make('permissions')
                    ->label('Permissions')
                    ->relationship('permissions', 'name')
                    ->options(fn () => Permission::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->columns(2)
                    ->searchable()
                    ->bulkToggleable(),
            ]);
    }
}
