<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Proyectos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Proyectos';

    protected static ?string $title = 'Proyectos';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.coming-soon';
}
