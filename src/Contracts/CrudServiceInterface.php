<?php

declare(strict_types=1);

namespace Didasto\Apilot\Contracts;

use Didasto\Apilot\Dto\PaginatedResult;
use Didasto\Apilot\Dto\PaginationParams;

interface CrudServiceInterface
{
    /**
     * @param array<string, mixed> $filters
     * @param array<string, string> $sorting
     */
    public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult;

    public function find(int|string $id): mixed;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): mixed;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): mixed;

    public function delete(int|string $id): bool;
}
