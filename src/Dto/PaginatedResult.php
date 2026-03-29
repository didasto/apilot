<?php

declare(strict_types=1);

namespace Didasto\Apilot\Dto;

readonly class PaginatedResult
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $currentPage,
    ) {}

    public function lastPage(): int
    {
        return (int) max(1, ceil($this->total / $this->perPage));
    }
}
