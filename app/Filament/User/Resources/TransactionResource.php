<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\TransactionResource\Pages;
use App\Filament\User\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Model;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make()->schema([
                    TextEntry::make('id')
                        ->label('Transaction ID'),

                    TextEntry::make('type')
                        ->label('Transaction Type',),

                    TextEntry::make('amount')
                        ->label('Amount'),

                    TextEntry::make('account_number')
                        ->label('Account Number'),

                    TextEntry::make('account_name')
                        ->label('Account Name'),

                    TextEntry::make('account_bank')
                        ->label('Account Bank'),

                    TextEntry::make('mdn')
                        ->label('Virtual Number'),

                    TextEntry::make('phone_number')
                        ->label('Airtime / Data Number'),

                    TextEntry::make('service')
                        ->label('Platform'),

                    TextEntry::make('message')
                        ->label('SMS'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            // 'create' => Pages\CreateTransaction::route('/create'),
            'view' => Pages\ViewTransaction::route('/{record}'),
            // 'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
