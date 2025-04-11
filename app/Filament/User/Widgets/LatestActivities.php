<?php

namespace App\Filament\User\Widgets;

use App\Models\RecentActivity;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestActivities extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn() => RecentActivity::query()->where('user_id', auth()->user()->id)
            )->defaultPaginationPageOption(5)
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->searchable()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'success' => 'success',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->searchable(),
            ]);
    }
}
