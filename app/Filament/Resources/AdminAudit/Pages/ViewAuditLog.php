<?php

namespace App\Filament\Resources\AdminAudit\Pages;

use Filament\Resources\Pages\ViewRecord;

class ViewAuditLog extends ViewRecord
{
    protected static string $resource = \App\Filament\Resources\AdminAudit\AuditLogResource::class;
}