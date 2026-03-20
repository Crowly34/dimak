<?php

namespace App\Search;

use App\Enums\TicketStatus;

class SearchQuery
{
    public function __construct(
        public readonly string $rawQuery,
        public readonly ?string $clientName = null,
        public readonly ?string $device = null,
        public readonly ?string $folio = null,
        public readonly ?TicketStatus $status = null,
        public readonly ?bool $paid = null,
        public readonly bool $isFallback = false,
    ) {}

    public static function fallback(string $rawQuery): self
    {
        return new self(rawQuery: $rawQuery, isFallback: true);
    }

    public static function fromArray(array $data, string $rawQuery): self
    {
        return new self(
            rawQuery: $rawQuery,
            clientName: ($data['client_name'] ?? null) ?: null,
            device: ($data['device'] ?? null) ?: null,
            folio: ($data['folio'] ?? null) ?: null,
            status: TicketStatus::tryFrom($data['status'] ?? ''),
            paid: match ($data['paid'] ?? '') {
                true, 'true' => true,
                false, 'false' => false,
                default => null,
            },
        );
    }
}
