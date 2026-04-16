<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Herramientas extends Page
{
    protected static ?string $navigationGroup = 'Admin';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Herramientas';

    protected static ?string $title = 'Admin - Herramientas';

    protected static ?string $slug = 'admin-herramientas';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.herramientas';
}
