<?php

declare(strict_types=1);

namespace Didasto\Apilot\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Didasto\Apilot\Enums\AllowedFilter;

trait HasFiltering
{
    protected function applyFiltering(Builder $query, Request $request): Builder
    {
        $filterParam = config('apilot.filtering.param', 'filter');
        $filters = $request->input($filterParam);

        if (empty($filters) || !is_array($filters)) {
            return $query;
        }

        foreach ($filters as $field => $value) {
            if (!isset($this->allowedFilters[$field])) {
                continue;
            }

            // Ignore empty/null filter values
            if ($value === null || $value === '') {
                continue;
            }

            // Ignore non-scalar filter values (e.g. array injection)
            if (!is_scalar($value)) {
                continue;
            }

            $filterType = $this->allowedFilters[$field];

            match ($filterType) {
                AllowedFilter::EXACT   => $query->where($field, '=', $value),
                AllowedFilter::PARTIAL => $query->where($field, 'LIKE', "%{$value}%"),
                AllowedFilter::SCOPE   => $query->{$field}($value),
            };
        }

        return $query;
    }
}
