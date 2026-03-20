<?php

use App\Ai\Agents\QueryParser;
use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Order;
use App\Models\Ticket;
use App\Search\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SearchService;
});

it('finds orders by client name via parsed query', function () {
    $client = Client::factory()->create(['name' => 'Juan Garcia']);
    $order = Order::factory()->for($client)->create();
    Ticket::factory()->for($order)->create(['device' => 'MacBook Pro']);

    // Another client that should NOT match
    $other = Client::factory()->create(['name' => 'Maria Lopez']);
    Order::factory()->for($other)->create();

    QueryParser::fake([
        ['client_name' => 'juan', 'device' => null, 'folio' => null, 'status' => null, 'paid' => null],
    ]);

    $query = $this->service->parse('juan');
    $results = $this->service->searchOrders($query);

    expect($results)->toHaveCount(1)
        ->and($results->first()->client->name)->toBe('Juan Garcia');
});

it('filters orders by ticket status', function () {
    $client = Client::factory()->create();
    $readyOrder = Order::factory()->for($client)->create();
    Ticket::factory()->for($readyOrder)->ready()->create();

    $pendingOrder = Order::factory()->for($client)->create();
    Ticket::factory()->for($pendingOrder)->create(['status' => TicketStatus::PendingDiagnosis]);

    QueryParser::fake([
        ['client_name' => null, 'device' => null, 'folio' => null, 'status' => 'ready', 'paid' => null],
    ]);

    $query = $this->service->parse('ready');
    $results = $this->service->searchOrders($query);

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($readyOrder->id);
});

it('falls back to ILIKE search by folio', function () {
    $client = Client::factory()->create();
    $order = Order::factory()->for($client)->create(['folio' => 'ABC-123']);
    Ticket::factory()->for($order)->create();

    QueryParser::fake(fn () => throw new \RuntimeException('AI is down'));

    $query = $this->service->parse('ABC-123');
    $results = $this->service->searchOrders($query);

    expect($query->isFallback)->toBeTrue()
        ->and($results)->toHaveCount(1)
        ->and($results->first()->folio)->toBe('ABC-123');
});

it('handles AI failure gracefully with fallback', function () {
    QueryParser::fake(fn () => throw new \RuntimeException('API error'));

    $query = $this->service->parse('anything');

    expect($query->isFallback)->toBeTrue()
        ->and($query->rawQuery)->toBe('anything');
});
