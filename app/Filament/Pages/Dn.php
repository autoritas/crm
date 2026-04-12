<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Dn extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'DN';

    protected static ?string $title = 'DN';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.coming-soon';
}
