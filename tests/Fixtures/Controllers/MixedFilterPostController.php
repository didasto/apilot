<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\Filters\IdFilter;
use Didasto\Apilot\Tests\Fixtures\Models\Post;

class MixedFilterPostController extends ModelCrudController
{
    protected string $model = Post::class;

    protected array $allowedFilters = [
        'id'       => IdFilter::class,
        'status'   => AllowedFilter::EQUALS,
        'title'    => [AllowedFilter::LIKE, AllowedFilter::EQUALS],
        'category' => AllowedFilter::SCOPE,
    ];
}
