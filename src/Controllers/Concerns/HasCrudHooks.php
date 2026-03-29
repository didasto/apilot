<?php

declare(strict_types=1);

namespace Didasto\Apilot\Controllers\Concerns;

use Illuminate\Http\Request;

trait HasCrudHooks
{
    // =============================================
    // INDEX Hooks
    // =============================================

    protected function modifyIndexQuery(mixed $query, Request $request): mixed
    {
        return $query;
    }

    protected function afterIndex(mixed $result, Request $request): mixed
    {
        return $result;
    }

    // =============================================
    // SHOW Hooks
    // =============================================

    protected function afterShow(mixed $item, Request $request): mixed
    {
        return $item;
    }

    // =============================================
    // STORE Hooks
    // =============================================

    protected function beforeStore(array $data, Request $request): array
    {
        return $data;
    }

    protected function afterStore(mixed $item, Request $request): mixed
    {
        return $item;
    }

    // =============================================
    // UPDATE Hooks
    // =============================================

    protected function beforeUpdate(mixed $item, array $data, Request $request): array
    {
        return $data;
    }

    protected function afterUpdate(mixed $item, Request $request): mixed
    {
        return $item;
    }

    // =============================================
    // DESTROY Hooks
    // =============================================

    protected function beforeDestroy(mixed $item, Request $request): bool
    {
        return true;
    }

    protected function afterDestroy(mixed $item, Request $request): void
    {
        // No-op by default
    }

    // =============================================
    // GLOBAL Hooks
    // =============================================

    protected function transformResponse(mixed $data, string $action, Request $request): mixed
    {
        return $data;
    }

    protected function getStatusCode(string $action): int
    {
        return match ($action) {
            'store'   => 201,
            'destroy' => 204,
            default   => 200,
        };
    }
}
