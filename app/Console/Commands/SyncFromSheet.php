<?php

namespace App\Console\Commands;

use App\Actions\SyncOrdersFromSheet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncFromSheet extends Command
{
    protected $signature = 'sheets:sync';

    protected $description = 'Sync orders from Google Sheets';

    public function handle(SyncOrdersFromSheet $action): int
    {
        $this->info('Syncing from Google Sheets...');

        try {
            $result = $action();
        } catch (\Throwable $e) {
            Log::error('Google Sheets sync failed', ['error' => $e->getMessage()]);
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Done. '.$result->summary());

        return self::SUCCESS;
    }
}
