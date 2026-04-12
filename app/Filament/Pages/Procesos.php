<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Procesos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Procesos';

    protected static ?string $title = 'Procesos';

    protected static ?int $navigationSort = 9;

    protected static string $view = 'filament.pages.coming-soon';
}
