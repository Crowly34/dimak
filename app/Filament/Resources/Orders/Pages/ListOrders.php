<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Imports\OrderImporter;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(OrderImporter::class),
            CreateAction::make(),
        ];
    }
}
