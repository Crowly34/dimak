<?php

namespace App\DTOs;

readonly class SyncResult
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
    ) {}

    public function summary(): string
    {
        return sprintf('%d new, %d updated, %d unchanged', $this->created, $this->updated, $this->skipped);
    }
}
