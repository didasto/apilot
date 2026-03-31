<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Didasto\Apilot\Attributes\ApiResource;
use Didasto\Apilot\Controllers\ServiceCrudController;
use Didasto\Apilot\Tests\Fixtures\Services\TagService;

#[ApiResource(path: '/tags', only: ['index', 'show'])]
class AttributeTagController extends ServiceCrudController
{
    protected string $serviceClass = TagService::class;
}
