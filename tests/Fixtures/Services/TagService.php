<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Services;

use Didasto\Apilot\Contracts\CrudServiceInterface;
use Didasto\Apilot\Dto\PaginatedResult;
use Didasto\Apilot\Dto\PaginationParams;
use stdClass;

class TagService implements CrudServiceInterface
{
    protected static array $tags = [];
    protected static int $nextId = 1;

    public static function reset(): void
    {
        static::$tags = [];
        static::$nextId = 1;
    }

    public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult
    {
        $items = array_values(static::$tags);

        // Apply filters
        if (!empty($filters['name'])) {
            $items = array_values(array_filter(
                $items,
                fn (stdClass $tag) => str_contains(strtolower($tag->name), strtolower($filters['name']))
            ));
        }

        if (!empty($filters['slug'])) {
            $items = array_values(array_filter(
                $items,
                fn (stdClass $tag) => $tag->slug === $filters['slug']
            ));
        }

        // Apply sorting
        if (!empty($sorting)) {
            usort($items, function (stdClass $a, stdClass $b) use ($sorting): int {
                foreach ($sorting as $field => $direction) {
                    $aVal = $a->{$field} ?? '';
                    $bVal = $b->{$field} ?? '';
                    $cmp = strcmp((string) $aVal, (string) $bVal);

                    if ($cmp !== 0) {
                        return $direction === 'desc' ? -$cmp : $cmp;
                    }
                }

                return 0;
            });
        }

        $total = count($items);

        // Apply pagination
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
        return static::$tags[(int) $id] ?? null;
    }

    public function create(array $data): mixed
    {
        $id = static::$nextId++;
        $tag = new stdClass();
        $tag->id = $id;
        $tag->name = $data['name'];
        $tag->slug = $data['slug'];
        $tag->created_at = date('Y-m-d H:i:s');

        static::$tags[$id] = $tag;

        return $tag;
    }

    public function update(int|string $id, array $data): mixed
    {
        $tag = static::$tags[(int) $id] ?? null;

        if ($tag === null) {
            return null;
        }

        if (isset($data['name'])) {
            $tag->name = $data['name'];
        }

        if (isset($data['slug'])) {
            $tag->slug = $data['slug'];
        }

        static::$tags[(int) $id] = $tag;

        return $tag;
    }

    public function delete(int|string $id): bool
    {
        if (!isset(static::$tags[(int) $id])) {
            return false;
        }

        unset(static::$tags[(int) $id]);

        return true;
    }
}
