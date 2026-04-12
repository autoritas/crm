<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Auditoria extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Auditoria';

    protected static ?string $title = 'Auditoria';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.coming-soon';
}
