<?php

use App\Ai\Agents\QueryParser;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('returns global search results for matching clients', function () {
    $client = Client::factory()->create(['name' => 'Maria Lopez', 'phone' => '555-1234']);
    Order::factory()->for($client)->count(2)->create();

    QueryParser::fake([
        ['client_name' => 'maria', 'device' => null, 'folio' => null, 'status' => null, 'paid' => null],
    ]);

    $results = ClientResource::getGlobalSearchResults('maria');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Maria Lopez')
        ->and($results->first()->details['Phone'])->toBe('555-1234')
        ->and($results->first()->details['Orders'])->toBe('2');
});
