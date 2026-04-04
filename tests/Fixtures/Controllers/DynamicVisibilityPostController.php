<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Illuminate\Http\Request;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;

class DynamicVisibilityPostController extends ModelCrudController
{
    protected string $model = Post::class;

    protected function visibleFields(Request $request): array
    {
        if ($request->headers->get('X-Role') === 'admin') {
            return ['id', 'title', 'body', 'status', 'created_at', 'updated_at'];
        }
        return ['id', 'title', 'status'];
    }

    protected function hiddenFields(Request $request): array
    {
        return [];
    }
}
