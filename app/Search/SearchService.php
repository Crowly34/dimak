<?php

namespace App\Search;

use App\Ai\Agents\QueryParser;
use App\Models\Client;
use App\Models\Order;
use App\Models\SearchLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchService
{
    private ?float $parseStartTime = null;

    private ?int $parseDurationMs = null;

    public function parse(string $input): SearchQuery
    {
        $input = trim($input);

        if ($input === '' || mb_strlen($input) <= 2) {
            $this->parseDurationMs = 0;

            return SearchQuery::fallback($input);
        }

        $this->parseStartTime = microtime(true);

        $query = Cache::remember('search:'.md5($input), 5, function () use ($input) {
            try {
                $result = (new QueryParser)->prompt($input);

                return SearchQuery::fromArray($result->toArray(), $input);
            } catch (\Throwable) {
                return SearchQuery::fallback($input);
            }
        });

        $this->parseDurationMs = (int) ((microtime(true) - $this->parseStartTime) * 1000);

        return $query;
    }

    /** @return Collection<int, Order> */
    public function searchOrders(SearchQuery $query, int $limit = 50): Collection
    {
        if ($query->rawQuery === '') {
            return collect();
        }

        $builder = Order::query()->with(['client', 'tickets']);

        if ($query->isFallback) {
            return $this->fallbackOrderSearch($builder, $query->rawQuery, $limit);
        }

        $hasCondition = false;

        if ($query->clientName !== null) {
            $builder->whereHas('client', function ($q) use ($query) {
                $this->applyFuzzyName($q, 'name', $query->clientName);
            });
            $hasCondition = true;
        }

        if ($query->device !== null) {
            $builder->whereHas('tickets', function ($q) use ($query) {
                $q->where('device', 'LIKE', "%{$query->device}%");
            });
            $hasCondition = true;
        }

        if ($query->folio !== null) {
            $builder->where('folio', 'LIKE', "%{$query->folio}%");
            $hasCondition = true;
        }

        if ($query->status !== null) {
            $builder->whereHas('tickets', function ($q) use ($query) {
                $q->where('status', $query->status);
            });
            $hasCondition = true;
        }

        if ($query->paid !== null) {
            $builder->where('paid', $query->paid);
            $hasCondition = true;
        }

        if (! $hasCondition) {
            return $this->fallbackOrderSearch($builder, $query->rawQuery, $limit);
        }

        return $builder->latest('received_at')->limit($limit)->get();
    }

    /** @return Collection<int, Client> */
    public function searchClients(SearchQuery $query, int $limit = 50): Collection
    {
        if ($query->rawQuery === '') {
            return collect();
        }

        $builder = Client::query()->with(['orders.tickets']);

        if ($query->isFallback) {
            return $this->fallbackClientSearch($builder, $query->rawQuery, $limit);
        }

        if ($query->clientName !== null) {
            $this->applyFuzzyName($builder, 'name', $query->clientName);
        } else {
            return $this->fallbackClientSearch($builder, $query->rawQuery, $limit);
        }

        return $builder->latest('updated_at')->limit($limit)->get();
    }

    public function log(SearchQuery $query, int $orderResults = 0, int $clientResults = 0): void
    {
        if ($query->rawQuery === '') {
            return;
        }

        $cacheKey = 'search_log:'.md5($query->rawQuery);

        $existing = Cache::get($cacheKey);

        if ($existing instanceof SearchLog) {
            $existing->update([
                'order_results' => max($existing->order_results, $orderResults),
                'client_results' => max($existing->client_results, $clientResults),
            ]);

            return;
        }

        $log = SearchLog::create([
            'query' => $query->rawQuery,
            'parsed_client' => $query->clientName,
            'parsed_device' => $query->device,
            'parsed_status' => $query->status?->value,
            'is_fallback' => $query->isFallback,
            'order_results' => $orderResults,
            'client_results' => $clientResults,
            'duration_ms' => $this->parseDurationMs ?? 0,
            'user_id' => Auth::id(),
        ]);

        Cache::put($cacheKey, $log, 10);
    }

    private function applyFuzzyName(Builder $query, string $column, string $term): void
    {
        if ($this->isPostgres()) {
            $query->where(function ($q) use ($column, $term) {
                $q->whereRaw("{$column} ILIKE ?", ["%{$term}%"])
                    ->orWhereRaw("similarity({$column}, ?) > 0.3", [$term]);
            });
        } else {
            $query->where($column, 'LIKE', "%{$term}%");
        }
    }

    /** @return Collection<int, Order> */
    private function fallbackOrderSearch(Builder $builder, string $rawQuery, int $limit): Collection
    {
        return $builder->where(function ($q) use ($rawQuery) {
            $q->where('folio', 'LIKE', "%{$rawQuery}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'LIKE', "%{$rawQuery}%"))
                ->orWhereHas('tickets', fn ($t) => $t->where('device', 'LIKE', "%{$rawQuery}%"));
        })->latest('received_at')->limit($limit)->get();
    }

    /** @return Collection<int, Client> */
    private function fallbackClientSearch(Builder $builder, string $rawQuery, int $limit): Collection
    {
        return $builder->where(function ($q) use ($rawQuery) {
            $q->where('name', 'LIKE', "%{$rawQuery}%")
                ->orWhere('phone', 'LIKE', "%{$rawQuery}%");
        })->latest('updated_at')->limit($limit)->get();
    }

    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
}
