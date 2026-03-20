<?php

use App\Ai\Agents\QueryParser;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Client;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('returns global search results for matching orders', function () {
    $client = Client::factory()->create(['name' => 'Juan Garcia']);
    $order = Order::factory()->for($client)->create(['folio' => 'ORD-001']);
    Ticket::factory()->for($order)->ready()->create(['device' => 'MacBook Pro']);

    QueryParser::fake([
        ['client_name' => 'juan', 'device' => null, 'folio' => null, 'status' => null, 'paid' => null],
    ]);

    $results = OrderResource::getGlobalSearchResults('juan');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toContain('Juan Garcia')
        ->and($results->first()->details['Device(s)'])->toContain('MacBook Pro');
});

it('includes status and paid info in result details', function () {
    $client = Client::factory()->create(['name' => 'Test Client']);
    $order = Order::factory()->for($client)->create(['paid' => true]);
    Ticket::factory()->for($order)->ready()->create();

    QueryParser::fake([
        ['client_name' => 'test', 'device' => null, 'folio' => null, 'status' => null, 'paid' => null],
    ]);

    $results = OrderResource::getGlobalSearchResults('test');
    $details = $results->first()->details;

    expect($details['Status'])->toBe('Ready')
        ->and($details['Paid'])->toBe('Yes');
});
