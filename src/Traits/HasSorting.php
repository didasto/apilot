<?php

declare(strict_types=1);

namespace Didasto\Apilot\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HasSorting
{
    protected function applySorting(Builder $query, Request $request): Builder
    {
        $sortParam = config('apilot.sorting.param', 'sort');
        $sortValue = $request->input($sortParam);

        // Ignore array injection (e.g. ?sort[]=title)
        if (empty($sortValue) || !is_string($sortValue)) {
            return $query;
        }

        $fields = explode(',', $sortValue);

        foreach ($fields as $field) {
            $field = trim($field);

            if ($field === '') {
                continue;
            }

            $direction = 'asc';

            if (str_starts_with($field, '-')) {
                $direction = 'desc';
                $field = substr($field, 1);
            }

            if (!in_array($field, $this->allowedSorts, true)) {
                continue;
            }

            $query->orderBy($field, $direction);
        }

        return $query;
    }
}
