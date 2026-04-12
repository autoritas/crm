<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Indicadores extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Indicadores';

    protected static ?string $title = 'Indicadores';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.coming-soon';
}
