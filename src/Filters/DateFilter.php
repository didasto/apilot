<?php

declare(strict_types=1);

namespace Didasto\Apilot\Filters;

use Didasto\Apilot\Enums\AllowedFilter;

class DateFilter extends FilterSet
{
    protected array $filters = [
        AllowedFilter::EQUALS,
        AllowedFilter::NOT_EQUALS,
        AllowedFilter::GT,
        AllowedFilter::LT,
        AllowedFilter::GTE,
        AllowedFilter::LTE,
        AllowedFilter::BETWEEN,
        AllowedFilter::IS_NULL,
        AllowedFilter::IS_NOT_NULL,
    ];
}
