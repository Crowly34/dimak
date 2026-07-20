<?php

use App\DTOs\SheetRow;
use App\Integrations\GoogleSheets\GoogleSheetsClient;
use App\Models\Client;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

function sheetRow(string $key): array
{
    static $data;
    $data ??= json_decode(file_get_contents(__DIR__.'/../../Fixtures/sheet-rows.json'), true);

    return $data[$key];
}

function fakeSheetClient(array $rawRows): void
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

function runSync(): \App\DTOs\SyncResult
{
    return app(\App\Actions\SyncOrdersFromSheet::class)();
}

// --- Create ---

it('creates a new order and ticket from a SheetRow', function () {
    fakeSheetClient([sheetRow('normal')]);

    $result = runSync();

    expect($result->created)->toBe(1);

    $order = Order::where('folio', '1001')->first();
    expect($order)->not->toBeNull()
        ->and($order->client->name)->toBe('Juan Garcia');

    $ticket = $order->tickets->first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->device)->toBe('MacBook Pro')
        ->and($ticket->device_serial)->toBe('C02ABC123')
        ->and($ticket->description)->toBe('Screen broken')
        ->and($ticket->observations)->toBe('en proceso');
});

it('stores sheet_row_hash on created tickets', function () {
    fakeSheetClient([sheetRow('normal')]);

    runSync();

    $ticket = Order::where('folio', '1001')->first()->tickets->first();
    expect($ticket->sheet_row_hash)->not->toBeNull()
        ->and($ticket->sheet_row_hash)->toHaveLength(32);
});

it('creates a new client when no match exists', function () {
    fakeSheetClient([sheetRow('normal')]);

    runSync();

    expect(Client::where('name', 'Juan Garcia')->exists())->toBeTrue();
});

it('matches an existing client by phone', function () {
    $existing = Client::factory()->create(['name' => 'J. Garcia', 'phone' => '3312345678']);

    fakeSheetClient([sheetRow('normal')]);
    runSync();

    expect(Client::count())->toBe(1);
    expect(Order::where('folio', '1001')->first()->client_id)->toBe($existing->id);
});

it('matches an existing client by name when phone differs', function () {
    $existing = Client::factory()->create(['name' => 'Juan Garcia', 'phone' => null]);

    fakeSheetClient([sheetRow('normal')]);
    runSync();

    expect(Client::count())->toBe(1);
    expect(Order::where('folio', '1001')->first()->client_id)->toBe($existing->id);
});

// --- Update ---

it('updates a ticket when hash differs', function () {
    fakeSheetClient([sheetRow('normal')]);
    runSync();

    // Change the observations
    $modified = sheetRow('normal');
    $modified[8] = 'Updated observations text';
    fakeSheetClient([$modified]);

    $result = runSync();

    expect($result->updated)->toBe(1);
    expect(Order::where('folio', '1001')->first()->tickets->first()->observations)
        ->toBe('Updated observations text');
});

it('re-encrypts password when sheet password changes', function () {
    fakeSheetClient([sheetRow('with_password')]);
    runSync();

    $ticket = Order::where('folio', '1005')->first()->tickets->first();
    $originalHash = $ticket->sheet_row_hash;

    // Change password in sheet
    $modified = sheetRow('with_password');
    $modified[7] = 'newPassword!';
    fakeSheetClient([$modified]);

    runSync();

    $ticket->refresh();
    expect($ticket->device_password)->toBe('newPassword!')
        ->and($ticket->sheet_row_hash)->not->toBe($originalHash);
});

it('re-infers status and location on update', function () {
    fakeSheetClient([sheetRow('normal')]);
    runSync();

    // Manually change status in DB to simulate Filament edit
    $ticket = Order::where('folio', '1001')->first()->tickets->first();
    $ticket->update(['status' => 'ready', 'location' => 'shop']);

    // Sheet still says "en proceso" — but now change observations to delivered
    $modified = sheetRow('normal');
    $modified[8] = 'Equipo entregado al cliente';
    fakeSheetClient([$modified]);

    runSync();

    $ticket->refresh();
    expect($ticket->status->value)->toBe('delivered')
        ->and($ticket->location->value)->toBe('delivered');
});

// --- Skip ---

it('skips rows where hash matches (unchanged)', function () {
    fakeSheetClient([sheetRow('normal')]);
    runSync();

    // Run again with same data
    fakeSheetClient([sheetRow('normal')]);
    $result = runSync();

    expect($result->skipped)->toBe(1)
        ->and($result->created)->toBe(0)
        ->and($result->updated)->toBe(0);
});

it('skips empty rows', function () {
    fakeSheetClient([sheetRow('empty'), sheetRow('normal')]);

    $result = runSync();

    expect($result->created)->toBe(1);
    expect(Order::count())->toBe(1);
});

// --- Folio ---

it('assigns synthetic folio when folio is empty', function () {
    fakeSheetClient([sheetRow('no_folio')]);

    runSync();

    expect(Order::where('folio', 'IMP-1')->exists())->toBeTrue();
});

// --- Status inference ---

it('infers delivered status from observations text', function () {
    fakeSheetClient([sheetRow('delivered')]);
    runSync();

    $ticket = Order::where('folio', '1002')->first()->tickets->first();
    expect($ticket->status->value)->toBe('delivered')
        ->and($ticket->location->value)->toBe('delivered');
});

it('infers no_repair status from observations text', function () {
    fakeSheetClient([sheetRow('no_repair')]);
    runSync();

    $ticket = Order::where('folio', '1003')->first()->tickets->first();
    expect($ticket->status->value)->toBe('no_repair');
});

it('defaults to pending_diagnosis when no pattern matches', function () {
    fakeSheetClient([sheetRow('with_password')]);
    runSync();

    $ticket = Order::where('folio', '1005')->first()->tickets->first();
    expect($ticket->status->value)->toBe('pending_diagnosis');
});
