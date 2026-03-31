<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Services;

use Didasto\Apilot\Dto\PaginatedResult;
use Didasto\Apilot\Dto\PaginationParams;
use Didasto\Apilot\Services\AbstractCrudService;
use stdClass;

class MinimalTagService extends AbstractCrudService
{
    protected static array $items = [];
    protected static int $nextId = 1;

    public static function reset(): void
    {
        static::$items = [];
        static::$nextId = 1;
    }

    public static function seed(): stdClass
    {
        $item       = new stdClass();
        $item->id         = static::$nextId++;
        $item->name       = 'Test Tag';
        $item->slug       = 'test-tag';
        $item->created_at = date('Y-m-d H:i:s');

        static::$items[$item->id] = $item;

        return $item;
    }

    public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult
    {
        $items = array_values(static::$items);

        return new PaginatedResult(
            items: $items,
            total: count($items),
            perPage: $pagination->perPage,
            currentPage: $pagination->page,
        );
    }

    public function find(int|string $id): mixed
    {
        return static::$items[(int) $id] ?? null;
    }
}
