<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AdminPage extends Page
{
    protected static ?string $navigationGroup = 'Admin';

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Comercial';

    protected static ?string $title = 'Admin - Comercial';

    protected static ?string $slug = 'admin-comercial';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.admin-comercial';
}
