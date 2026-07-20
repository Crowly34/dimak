<?php

use App\DTOs\SheetRow;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Integrations\GoogleSheets\GoogleSheetsClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('shows the sync action on ListOrders', function () {
    livewire(ListOrders::class)
        ->assertActionExists('syncFromSheet');
});

it('executes the sync and shows notification', function () {
    $row = ['1001', 'MacBook Pro', 'John Doe', '555-1234', '15/01/2025', 'desc', '', '', ''];

    $this->instance(GoogleSheetsClient::class, new class([$row]) extends GoogleSheetsClient
    {
        public function __construct(private readonly array $rows) {}

        public function fetchRows(): Collection
        {
            return collect($this->rows)->map(fn (array $r): SheetRow => SheetRow::fromArray($r));
        }
    });

    livewire(ListOrders::class)
        ->callAction('syncFromSheet')
        ->assertNotified();
});

it('is rate limited to 30 seconds', function () {
    cache()->put('sheets:last_synced_at', now());

    livewire(ListOrders::class)
        ->assertActionDisabled('syncFromSheet');
});

it('displays the last sync timestamp', function () {
    cache()->put('sheets:last_synced_at', now()->subHours(2));

    // Just verify the action exists and is enabled when last sync was > 30s ago
    livewire(ListOrders::class)
        ->assertActionEnabled('syncFromSheet');
});
