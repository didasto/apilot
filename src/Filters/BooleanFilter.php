<?php

declare(strict_types=1);

namespace Didasto\Apilot\Filters;

use Didasto\Apilot\Enums\AllowedFilter;

class BooleanFilter extends FilterSet
{
    protected array $filters = [
        AllowedFilter::EQUALS,
        AllowedFilter::IS_NULL,
        AllowedFilter::IS_NOT_NULL,
    ];
}
