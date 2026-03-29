<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;

class MinimalController extends ModelCrudController
{
    protected string $model = Post::class;
    // No formRequestClass, no resourceClass, no filters, no sorts.
}
