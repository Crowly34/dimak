<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\RelationManagers\TicketsRelationManager;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
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

    public static function getGlobalSearchResults(string $search): Collection
    {
        $service = app(SearchService::class);
        $query = $service->parse($search);
        $orders = $service->searchOrders($query);
        $service->log($query, orderResults: $orders->count());

        return $orders->map(function (Order $order) {
            $devices = $order->tickets->pluck('device')->filter()->join(', ');
            $statuses = $order->tickets->pluck('status')->map->getLabel()->join(', ');

            return new GlobalSearchResult(
                title: "#{$order->folio} — {$order->client->name}",
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
