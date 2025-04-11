<?php

namespace App\Filament\User\Pages;

use App\Models\Transaction;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class Transactions extends Page implements HasTable
{

    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.user.pages.transactions';

    protected function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->where('user_id', auth()->user()->id)
            )
            ->columns([
                TextColumn::make('type')
                    ->label('Transaction Type')
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        return in_array($state, ['Tellabot', 'Daisy']) ? 'Virtual Number' : $state;
                    }),

                    TextColumn::make('amount')
                    ->label('Amount')
                    ->searchable(),

                    TextColumn::make('account_number')
                    ->label('Account Number')
                    ->searchable(),

                    TextColumn::make('account_name')
                    ->label('Account Name')
                    ->searchable(),

                    TextColumn::make('account_bank')
                    ->label('Account Bank')
                    ->searchable(),
                    
                TextColumn::make('mdn')
                    ->label('Virtual Number')
                    ->searchable(),

                    TextColumn::make('phone_number')
                    ->label('Airtime / Data Number')
                    ->searchable(),

                TextColumn::make('service')
                    ->label('Platform')
                    ->searchable()
                    ->formatStateUsing(function ($state, $record) {
                        if (in_array($record->type, ['Tellabot', 'Daisy'])) {
                            return $record->service;
                        }
                        return $state;
                    }),

            
                TextColumn::make('message')
                    ->label('SMS'),
            ])
            ->filters([
                // Add filters here if needed
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
            
                
}
