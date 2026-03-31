<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Services;

use Didasto\Apilot\Contracts\CrudServiceInterface;
use Didasto\Apilot\Dto\PaginatedResult;
use Didasto\Apilot\Dto\PaginationParams;

class OperatorFilterTagService implements CrudServiceInterface
{
    public static array $lastFilters = [];

    public static function reset(): void
    {
        static::$lastFilters = [];
    }

    public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult
    {
        static::$lastFilters = $filters;

        return new PaginatedResult(
            items: [],
            total: 0,
            perPage: $pagination->perPage,
            currentPage: $pagination->page,
        );
    }

    public function find(int|string $id): mixed
    {
        return null;
    }

    public function create(array $data): mixed
    {
        return null;
    }

    public function update(int|string $id, array $data): mixed
    {
        return null;
    }

    public function delete(int|string $id): bool
    {
        return true;
    }
}
