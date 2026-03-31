<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Didasto\Apilot\Controllers\ServiceCrudController;
use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\Tests\Fixtures\Services\OperatorFilterTagService;

class OperatorFilterTagController extends ServiceCrudController
{
    protected string $serviceClass = OperatorFilterTagService::class;

    protected array $allowedFilters = [
        'id'   => [AllowedFilter::GT, AllowedFilter::LTE],
        'name' => [AllowedFilter::EQUALS, AllowedFilter::LIKE, AllowedFilter::IN],
    ];
}
