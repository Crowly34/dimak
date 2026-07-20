<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\SyncOrdersFromSheet;
use App\Filament\Imports\OrderImporter;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->syncFromSheetAction(),
            ImportAction::make()
                ->importer(OrderImporter::class),
            CreateAction::make(),
        ];
    }

    private function syncFromSheetAction(): Action
    {
        $lastSyncedAt = cache('sheets:last_synced_at');
        $lastSyncedAt = $lastSyncedAt instanceof Carbon ? $lastSyncedAt : null;

        $label = 'Sync from Sheet';

        if ($lastSyncedAt) {
            $label .= ' (last: '.$lastSyncedAt->diffForHumans().')';
        }

        $isRateLimited = $lastSyncedAt && $lastSyncedAt->diffInSeconds(now()) < 30;

        return Action::make('syncFromSheet')
            ->label($label)
            ->icon(Heroicon::ArrowPath)
            ->disabled($isRateLimited)
            ->action(function (): void {
                try {
                    $result = app(SyncOrdersFromSheet::class)();

                    Notification::make()
                        ->title('Sync complete')
                        ->body($result->summary())
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Sync failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
