<?php

namespace App\Data;

final readonly class IngestSummary
{
    public function __construct(
        public int $read,
        public int $inserted,
        public int $skipped,
        public ?string $dateFrom,
        public ?string $dateTo,
    ) {}

    public function duplicates(): int
    {
        return max(0, $this->read - $this->inserted - $this->skipped);
    }
}
