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

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $rawQuery): self
    {
        $clientName = $data['client_name'] ?? null;
        $device = $data['device'] ?? null;
        $folio = $data['folio'] ?? null;
        $status = $data['status'] ?? null;
        $paid = $data['paid'] ?? null;

        return new self(
            rawQuery: $rawQuery,
            clientName: is_string($clientName) ? ($clientName ?: null) : null,
            device: is_string($device) ? ($device ?: null) : null,
            folio: is_string($folio) ? ($folio ?: null) : null,
            status: is_string($status) ? TicketStatus::tryFrom($status) : null,
            paid: match (true) {
                $paid === true, $paid === 'true' => true,
                $paid === false, $paid === 'false' => false,
                default => null,
            },
        );
    }
}
