<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['is_super_admin'] = $this->record->hasRole('super_admin');

        return $data;
    }

    protected function afterSave(): void
    {
        $superAdmin = (bool) ($this->form->getRawState()['is_super_admin'] ?? false);

        if ($superAdmin) {
            if (! $this->record->hasRole('super_admin')) {
                $this->record->assignRole('super_admin');
            }
        } else {
            if ($this->record->hasRole('super_admin')) {
                $this->record->removeRole('super_admin');
            }
        }
    }
}
