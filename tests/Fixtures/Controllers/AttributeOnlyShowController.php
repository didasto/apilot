<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Didasto\Apilot\Attributes\ApiResource;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;

#[ApiResource(path: '/items', only: ['show'])]
class AttributeOnlyShowController extends ModelCrudController
{
    protected string $model = Post::class;
}
