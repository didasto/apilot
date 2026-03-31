<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Filters;

use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\Filters\FilterSet;

class CustomStatusFilter extends FilterSet
{
    protected array $filters = [
        AllowedFilter::EQUALS,
        AllowedFilter::NOT_EQUALS,
        AllowedFilter::IN,
    ];
}
