<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Services;

use Didasto\Apilot\Contracts\CrudServiceInterface;
use Didasto\Apilot\Dto\PaginatedResult;
use Didasto\Apilot\Dto\PaginationParams;
use stdClass;

class SecretTagService implements CrudServiceInterface
{
    protected static array $items = [];
    protected static int $nextId = 1;

    public static function reset(): void
    {
        static::$items = [];
        static::$nextId = 1;
    }

    public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult
    {
        $items = array_values(static::$items);
        $total = count($items);

        $offset = ($pagination->page - 1) * $pagination->perPage;
        $items = array_values(array_slice($items, $offset, $pagination->perPage));

        return new PaginatedResult(
            items: $items,
            total: $total,
            perPage: $pagination->perPage,
            currentPage: $pagination->page,
        );
    }

    public function find(int|string $id): mixed
    {
        return static::$items[(int) $id] ?? null;
    }

    public function create(array $data): mixed
    {
        $id = static::$nextId++;
        $item = new stdClass();
        $item->id = $id;
        $item->name = $data['name'] ?? '';
        $item->secret = 'top-secret-value';
        $item->created_at = date('Y-m-d H:i:s');

        static::$items[$id] = $item;

        return $item;
    }

    public function update(int|string $id, array $data): mixed
    {
        $item = static::$items[(int) $id] ?? null;
        if ($item === null) {
            return null;
        }
        if (isset($data['name'])) {
            $item->name = $data['name'];
        }
        static::$items[(int) $id] = $item;
        return $item;
    }

    public function delete(int|string $id): bool
    {
        if (!isset(static::$items[(int) $id])) {
            return false;
        }
        unset(static::$items[(int) $id]);
        return true;
    }
}
