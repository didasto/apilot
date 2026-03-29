<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Didasto\Apilot\Controllers\ServiceCrudController;
use Didasto\Apilot\Tests\Fixtures\Requests\TagRequest;
use Didasto\Apilot\Tests\Fixtures\Resources\TagResource;
use Didasto\Apilot\Tests\Fixtures\Services\TagService;

class TagController extends ServiceCrudController
{
    protected string $serviceClass = TagService::class;
    protected ?string $formRequestClass = TagRequest::class;
    protected ?string $resourceClass = TagResource::class;

    protected array $allowedFilters = ['name', 'slug'];
    protected array $allowedSorts = ['name', 'created_at'];
}
