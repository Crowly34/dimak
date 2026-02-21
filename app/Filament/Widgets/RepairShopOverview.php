<?php

namespace App\Filament\Widgets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RepairShopOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Open Tickets', Ticket::whereNotIn('status', [TicketStatus::Delivered->value, TicketStatus::NoRepair->value])->count())
                ->description('Currently active repairs')
                ->color('info'),
            Stat::make('Waiting Parts', Ticket::where('status', TicketStatus::WaitingPart->value)->count())
                ->description('Pending part arrival')
                ->color('warning'),
            Stat::make('Ready for Pickup', Ticket::where('status', TicketStatus::Ready->value)->count())
                ->description('Repairs completed')
                ->color('success'),
            Stat::make('Unpaid Ready', Ticket::where('status', TicketStatus::Ready->value)->whereHas('order', fn ($q) => $q->where('paid', false))->count())
                ->description('Ready but not yet paid')
                ->color('danger'),
        ];
    }
}
