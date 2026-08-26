<?php

use App\DTOs\SheetRow;
use App\Models\SearchLog;
use App\Search\SearchQuery;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

pest()->use(RefreshDatabase::class);

// phpunit.xml pins CACHE_STORE=array, and the array store hands back the
// original instance without ever serializing. These tests reach for the
// database store on purpose: it is what .env configures and the only place
// cache.serializable_classes has any effect.
function serializingCache(): Repository
{
    return Cache::store('database');
}

it('restores a SearchQuery cached by the search service', function () {
    $query = SearchQuery::fallback('macbook screen');

    serializingCache()->put('search:test', $query, 60);

    expect(serializingCache()->get('search:test'))
        ->toBeInstanceOf(SearchQuery::class)
        ->rawQuery->toBe('macbook screen');
});

it('restores a SearchLog cached by the search service', function () {
    $log = SearchLog::create([
        'query' => 'macbook screen',
        'is_fallback' => false,
        'order_results' => 2,
        'client_results' => 1,
        'duration_ms' => 12,
    ]);

    serializingCache()->put('search_log:test', $log, 60);
    $restored = serializingCache()->get('search_log:test');

    // SearchService gates its update path on this instanceof, so an
    // unlisted class would silently fall through to a duplicate row.
    expect($restored)->toBeInstanceOf(SearchLog::class)
        ->and($restored->query)->toBe('macbook screen')
        ->and($restored->order_results)->toBe(2);
});

it('restores the Carbon timestamp written by the sheet sync', function () {
    serializingCache()->put('sheets:last_synced_at', now(), 60);

    expect(serializingCache()->get('sheets:last_synced_at'))
        ->toBeInstanceOf(Carbon::class);
});

it('refuses to restore a class missing from the allow list', function () {
    serializingCache()->put('unlisted', SheetRow::fromArray(['1001', 'iMac']), 60);

    expect(serializingCache()->get('unlisted'))->not->toBeInstanceOf(SheetRow::class);
});
