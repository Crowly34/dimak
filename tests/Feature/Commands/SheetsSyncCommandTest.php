<?php

use App\DTOs\SheetRow;
use App\Integrations\GoogleSheets\GoogleSheetsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

function fakeSheetClientForCommand(array $rawRows): void
{
    $mock = new class($rawRows) extends GoogleSheetsClient
    {
        /** @param array<int, array<int, string>> $rows */
        public function __construct(private readonly array $rows) {}

        public function fetchRows(): Collection
        {
            return collect($this->rows)->map(fn (array $r): SheetRow => SheetRow::fromArray($r));
        }
    };

    test()->instance(GoogleSheetsClient::class, $mock);
}

it('runs the sync and outputs summary', function () {
    $row = json_decode(file_get_contents(__DIR__.'/../../Fixtures/sheet-rows.json'), true)['normal'];

    fakeSheetClientForCommand([$row]);

    $this->artisan('sheets:sync')
        ->expectsOutputToContain('Syncing from Google Sheets')
        ->expectsOutputToContain('1 new')
        ->assertSuccessful();
});

it('handles API errors gracefully and returns failure', function () {
    $this->instance(GoogleSheetsClient::class, new class extends GoogleSheetsClient
    {
        public function __construct() {}

        public function fetchRows(): Collection
        {
            throw new \RuntimeException('API unreachable');
        }
    });

    $this->artisan('sheets:sync')
        ->expectsOutputToContain('Sync failed')
        ->assertFailed();
});
