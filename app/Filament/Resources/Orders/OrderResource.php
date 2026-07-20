<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\RelationManagers\TicketsRelationManager;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Client;
use App\Models\Order;
use App\Models\Ticket;
use App\Search\SearchService;
use BackedEnum;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $recordTitleAttribute = 'folio';

    protected static ?int $globalSearchSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    /**
     * @return Collection<int, GlobalSearchResult>
     */
    public static function getGlobalSearchResults(string $search): Collection
    {
        $service = app(SearchService::class);
        $query = $service->parse($search);
        $orders = $service->searchOrders($query);
        $service->log($query, orderResults: $orders->count());

        return $orders->map(function (Order $order) {
            $devices = $order->tickets
                ->map(fn (Ticket $ticket) => $ticket->device)
                ->filter(fn (string $device) => $device !== '')
                ->implode(', ');
            $statuses = $order->tickets
                ->map(fn (Ticket $ticket) => $ticket->status->getLabel())
                ->implode(', ');

            $client = $order->client;
            $clientName = $client instanceof Client ? $client->name : 'Unknown';

            return new GlobalSearchResult(
                title: "#{$order->folio} — {$clientName}",
                url: static::getUrl('edit', ['record' => $order]),
                details: [
                    'Device(s)' => $devices ?: '—',
                    'Status' => $statuses ?: '—',
                    'Paid' => $order->paid ? 'Yes' : 'No',
                ],
            );
        })->filter();
    }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TicketsRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
