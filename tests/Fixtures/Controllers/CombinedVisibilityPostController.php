<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;

class CombinedVisibilityPostController extends ModelCrudController
{
    protected string $model = Post::class;
    // Whitelist includes 'body', but blacklist removes it — body will NOT appear
    protected array $visibleFields = ['id', 'title', 'body', 'status'];
    protected array $hiddenFields = ['body'];
}
