<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\RuntimeConfigProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    RuntimeConfigProvider::class,
];
