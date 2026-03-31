<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Didasto\Apilot\Attributes\ApiResource;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Resources\PostResource;

#[ApiResource(path: '/posts')]
class AttributePostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $resourceClass = PostResource::class;
}
