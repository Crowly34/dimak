<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Schemas\ClientForm;
use App\Filament\Resources\Clients\Tables\ClientsTable;
use App\Models\Client;
use App\Search\SearchService;
use BackedEnum;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $globalSearchSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getGlobalSearchResults(string $search): Collection
    {
        $service = app(SearchService::class);
        $query = $service->parse($search);
        $clients = $service->searchClients($query);
        $service->log($query, clientResults: $clients->count());

        return $clients->map(function (Client $client) {
            return new GlobalSearchResult(
                title: $client->name,
                url: static::getUrl('edit', ['record' => $client]),
                details: [
                    'Phone' => $client->phone ?? '—',
                    'Orders' => (string) $client->orders->count(),
                ],
            );
        })->filter();
    }

    public static function form(Schema $schema): Schema
    {
        return ClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientsTable::configure($table);
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
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
