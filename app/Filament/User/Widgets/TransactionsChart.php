<?php

namespace App\Filament\User\Widgets;

use App\Models\Transaction;
use Filament\Forms\Components\Builder;
use Filament\Widgets\ChartWidget;

class TransactionsChart extends ChartWidget
{
    protected static ?string $heading = 'Transactions Chart';

    protected int | string | array $columnSpan = 'full';

    protected function getQuery()
    {
        return Transaction::query()
            ->where('user_id', auth()->user()->id)
            ->whereYear('created_at', now()->year)
            ->selectRaw('day(created_at) as day, count(*) as count')
            ->groupBy('day')
            ->orderBy('day');
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Transactions',
                    'data' => $this->getQuery()->pluck('count')->toArray(),
                ],
            ],
            'labels' => $this->getQuery()->pluck('day')->map(fn($day) => now()->year . '-' . str_pad($day, 2, '0', STR_PAD_LEFT))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
