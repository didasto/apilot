<?php

declare(strict_types=1);

namespace Didasto\Apilot\Services;

use Didasto\Apilot\Contracts\CrudServiceInterface;
use Didasto\Apilot\Dto\PaginatedResult;
use Didasto\Apilot\Dto\PaginationParams;
use Didasto\Apilot\Exceptions\NotImplementedException;

abstract class AbstractCrudService implements CrudServiceInterface
{
    public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult
    {
        throw new NotImplementedException(static::class . '::list');
    }

    public function find(int|string $id): mixed
    {
        throw new NotImplementedException(static::class . '::find');
    }

    public function create(array $data): mixed
    {
        throw new NotImplementedException(static::class . '::create');
    }

    public function update(int|string $id, array $data): mixed
    {
        throw new NotImplementedException(static::class . '::update');
    }

    public function delete(int|string $id): bool
    {
        throw new NotImplementedException(static::class . '::delete');
    }
}
