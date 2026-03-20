<?php

use App\Enums\TicketStatus;
use App\Search\SearchQuery;

it('creates a fallback query with isFallback true', function () {
    $query = SearchQuery::fallback('some input');

    expect($query->rawQuery)->toBe('some input')
        ->and($query->isFallback)->toBeTrue()
        ->and($query->clientName)->toBeNull();
});

it('maps status string to TicketStatus enum via fromArray', function () {
    $query = SearchQuery::fromArray([
        'client_name' => 'juan',
        'status' => 'ready',
    ], 'juan ready');

    expect($query->clientName)->toBe('juan')
        ->and($query->status)->toBe(TicketStatus::Ready)
        ->and($query->isFallback)->toBeFalse();
});

it('handles all-null data gracefully via fromArray', function () {
    $query = SearchQuery::fromArray([
        'client_name' => null,
        'device' => null,
        'folio' => null,
        'status' => null,
        'paid' => null,
    ], 'raw input');

    expect($query->rawQuery)->toBe('raw input')
        ->and($query->clientName)->toBeNull()
        ->and($query->status)->toBeNull()
        ->and($query->isFallback)->toBeFalse();
});

it('treats empty strings and sentinel values as null in fromArray', function () {
    $query = SearchQuery::fromArray([
        'client_name' => '',
        'device' => '',
        'folio' => '',
        'status' => 'none',
        'paid' => 'none',
    ], 'raw input');

    expect($query->clientName)->toBeNull()
        ->and($query->device)->toBeNull()
        ->and($query->folio)->toBeNull()
        ->and($query->status)->toBeNull()
        ->and($query->paid)->toBeNull();
});

it('maps paid string values to boolean or null', function () {
    $unpaid = SearchQuery::fromArray(['paid' => 'false'], 'no pagado');
    $paid = SearchQuery::fromArray(['paid' => 'true'], 'pagado');
    $unset = SearchQuery::fromArray(['paid' => 'none'], 'laptops');

    expect($unpaid->paid)->toBeFalse()
        ->and($paid->paid)->toBeTrue()
        ->and($unset->paid)->toBeNull();
});
