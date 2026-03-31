<?php

declare(strict_types=1);

namespace Didasto\Apilot\Filters;

use Didasto\Apilot\Enums\AllowedFilter;

class NumericFilter extends FilterSet
{
    protected array $filters = [
        AllowedFilter::EQUALS,
        AllowedFilter::NOT_EQUALS,
        AllowedFilter::IN,
        AllowedFilter::NOT_IN,
        AllowedFilter::GT,
        AllowedFilter::LT,
        AllowedFilter::GTE,
        AllowedFilter::LTE,
        AllowedFilter::BETWEEN,
    ];
}
