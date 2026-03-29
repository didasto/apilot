<?php

declare(strict_types=1);

namespace Didasto\Apilot\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

interface CrudFilterable
{
    public function applyFiltering(Builder $query, Request $request): Builder;
}
