<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ChatPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Chat';

    protected static ?string $title = 'Chat';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.coming-soon';
}
