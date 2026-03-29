<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\PostRequest;
use Didasto\Apilot\Tests\Fixtures\Resources\PostResource;

class PostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    protected ?string $resourceClass = PostResource::class;

    protected array $allowedFilters = [
        'status' => AllowedFilter::EXACT,
        'title'  => AllowedFilter::PARTIAL,
    ];

    protected array $allowedSorts = ['title', 'created_at'];
}
