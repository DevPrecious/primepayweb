<?php

namespace App\Filament\User\Widgets;

use App\Models\RecentActivity;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Balance', auth()->user()->wallet->balance ?? 0)->icon('heroicon-o-wallet')->color('success'),
            Stat::make('Total Transactions',  Transaction::where('user_id', auth()->user()->id)->count())->icon('heroicon-o-credit-card')->color('success'),
            Stat::make('Recent Activity Logs',  RecentActivity::where('user_id', auth()->user()->id)->count())->icon('heroicon-o-document-text')->color('success'),
        ];
    }
}
