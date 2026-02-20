<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RepairShopOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Open Orders', Order::whereNotIn('status', ['delivered', 'no_repair'])->count())
                ->description('Currently active repairs')
                ->color('info'),
            Stat::make('Waiting Parts', Order::where('status', 'waiting_part')->count())
                ->description('Pending part arrival')
                ->color('warning'),
            Stat::make('Ready for Pickup', Order::where('status', 'ready')->count())
                ->description('Repairs completed')
                ->color('success'),
            Stat::make('Unpaid Ready', Order::where('status', 'ready')->where('paid', false)->count())
                ->description('Ready but not yet paid')
                ->color('danger'),
        ];
    }
}
