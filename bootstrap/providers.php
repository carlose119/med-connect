<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\DoctorPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    DoctorPanelProvider::class,
];
