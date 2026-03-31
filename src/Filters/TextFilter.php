<?php

declare(strict_types=1);

namespace Didasto\Apilot\Filters;

use Didasto\Apilot\Enums\AllowedFilter;

class TextFilter extends FilterSet
{
    protected array $filters = [
        AllowedFilter::EQUALS,
        AllowedFilter::NOT_EQUALS,
        AllowedFilter::LIKE,
        AllowedFilter::NOT_LIKE,
        AllowedFilter::IN,
        AllowedFilter::IS_NULL,
        AllowedFilter::IS_NOT_NULL,
    ];
}
