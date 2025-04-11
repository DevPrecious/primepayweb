<?php

namespace App\Filament\User\Resources\TransactionResource\Pages;

use App\Filament\User\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;
}
