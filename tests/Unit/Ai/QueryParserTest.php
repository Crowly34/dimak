<?php

use App\Ai\Agents\QueryParser;
use App\Enums\TicketStatus;
use App\Search\SearchQuery;

it('parses a client name and device from structured output', function () {
    QueryParser::fake([
        ['client_name' => 'juan', 'device' => 'macbook', 'folio' => '', 'status' => 'none', 'paid' => 'none'],
    ]);

    $result = (new QueryParser)->prompt("juan's macbook");
    $query = SearchQuery::fromArray($result->toArray(), "juan's macbook");

    expect($query->clientName)->toBe('juan')
        ->and($query->device)->toBe('macbook')
        ->and($query->folio)->toBeNull()
        ->and($query->paid)->toBeNull();
});

it('parses payment and status filters', function () {
    QueryParser::fake([
        ['client_name' => '', 'device' => '', 'folio' => '', 'status' => 'ready', 'paid' => 'false'],
    ]);

    $result = (new QueryParser)->prompt('unpaid ready');
    $query = SearchQuery::fromArray($result->toArray(), 'unpaid ready');

    expect($query->paid)->toBeFalse()
        ->and($query->status)->toBe(TicketStatus::Ready);
});
