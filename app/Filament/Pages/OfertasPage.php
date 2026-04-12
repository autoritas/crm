<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class OfertasPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Ofertas';

    protected static ?string $title = 'Ofertas';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.coming-soon';
}
