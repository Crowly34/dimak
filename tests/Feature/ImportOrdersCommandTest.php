<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Writes a temporary CSV file with 4 skipped header rows followed by $rows.
 * Returns the file path; caller is responsible for unlinking.
 */
function csvFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'import_orders_test_');
    $handle = fopen($path, 'w');
    $empty = ['', '', '', '', '', '', '', '', ''];

    fputcsv($handle, $empty); // filter row
    fputcsv($handle, ['Folio', 'Device', 'Client', 'Phone', 'Received', 'Description', 'Serial', 'Password', 'Observations']);
    fputcsv($handle, $empty);
    fputcsv($handle, $empty);

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);

    return $path;
}

test('imports an order with a child ticket from a CSV row', function (): void {
    $path = csvFile([
        ['1001', 'MacBook Pro', 'John Doe', '555-1234', '15/01/2025', 'Screen broken', 'C02ABC123', '', ''],
    ]);

    $this->artisan('import:orders', ['file' => $path])->assertSuccessful();

    $order = Order::where('folio', '1001')->first();
    expect($order)->not->toBeNull()
        ->and($order->client->name)->toBe('John Doe');

    $ticket = $order->tickets->first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->device)->toBe('MacBook Pro')
        ->and($ticket->device_serial)->toBe('C02ABC123');

    unlink($path);
});

test('skips rows with duplicate folios', function (): void {
    Order::factory()->create(['folio' => '2001']);

    $path = csvFile([
        ['2001', 'iMac', 'Jane Doe', '555-9999', '01/02/2025', '', '', '', ''],
    ]);

    $this->artisan('import:orders', ['file' => $path])
        ->expectsOutputToContain('Skipping duplicate folio: 2001')
        ->assertSuccessful();

    expect(Order::where('folio', '2001')->count())->toBe(1);

    unlink($path);
});

test('deduplicates client by phone number', function (): void {
    $existing = Client::factory()->create(['name' => 'John Doe', 'phone' => '555-1234']);

    $path = csvFile([
        ['3001', 'MacBook Air', 'J. Doe', '555-1234', '01/03/2025', '', '', '', ''],
    ]);

    $this->artisan('import:orders', ['file' => $path])->assertSuccessful();

    expect(Client::count())->toBe(1);
    expect(Order::where('folio', '3001')->first()?->client_id)->toBe($existing->id);

    unlink($path);
});

test('deduplicates client by name when phone does not match', function (): void {
    $existing = Client::factory()->create(['name' => 'John Doe', 'phone' => null]);

    $path = csvFile([
        ['4001', 'iPhone 15', 'John Doe', '', '01/04/2025', '', '', '', ''],
    ]);

    $this->artisan('import:orders', ['file' => $path])->assertSuccessful();

    expect(Client::count())->toBe(1);
    expect(Order::where('folio', '4001')->first()?->client_id)->toBe($existing->id);

    unlink($path);
});

test('dry-run outputs rows without writing to the database', function (): void {
    $path = csvFile([
        ['5001', 'Mac mini', 'Bob Smith', '555-7777', '01/05/2025', '', '', '', ''],
    ]);

    $this->artisan('import:orders', ['file' => $path, '--dry-run' => true])
        ->expectsOutputToContain('[DRY-RUN]')
        ->assertSuccessful();

    expect(Order::count())->toBe(0);
    expect(Ticket::count())->toBe(0);

    unlink($path);
});

test('returns failure when the file does not exist', function (): void {
    $this->artisan('import:orders', ['file' => '/nonexistent/path/file.csv'])
        ->expectsOutputToContain('File not found')
        ->assertFailed();
});

test('observations text is used to infer ticket status', function (): void {
    $path = csvFile([
        ['6001', 'MacBook Pro', 'Client A', '', '01/06/2025', '', '', '', 'Equipo entregado al cliente'],
    ]);

    $this->artisan('import:orders', ['file' => $path])->assertSuccessful();

    $ticket = Order::where('folio', '6001')->first()->tickets->first();
    expect($ticket->status->value)->toBe('delivered');

    unlink($path);
});
