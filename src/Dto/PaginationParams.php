<?php

declare(strict_types=1);

namespace Didasto\Apilot\Dto;

readonly class PaginationParams
{
    public function __construct(
        public int $page,
        public int $perPage,
    ) {}
}
