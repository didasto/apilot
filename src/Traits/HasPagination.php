<?php

declare(strict_types=1);

namespace Didasto\Apilot\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait HasPagination
{
    protected function applyPagination(Builder $query, Request $request): LengthAwarePaginator
    {
        $perPageParam = config('apilot.pagination.per_page_param', 'per_page');
        $defaultPerPage = $this->defaultPerPage ?? config('apilot.pagination.default_per_page', 15);
        $maxPerPage = config('apilot.pagination.max_per_page', 100);

        $rawPerPage = $request->input($perPageParam, $defaultPerPage);
        $perPage = is_numeric($rawPerPage) ? (int) $rawPerPage : (int) $defaultPerPage;
        $perPage = max(1, min($perPage, $maxPerPage));

        // Default to 1 for non-positive or non-numeric page values
        $rawPage = $request->input('page', 1);
        $page = is_numeric($rawPage) ? (int) $rawPage : 1;
        $page = max(1, $page);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
