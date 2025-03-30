<?php

namespace App\Filament\User\Pages;

use Filament\Pages\Page;

class VirtualNumbers extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.user.pages.virtual-numbers';

    protected static ?string $navigationGroup = 'Virtual Number';

    protected ?string $heading = 'My Virtual Number';
}
