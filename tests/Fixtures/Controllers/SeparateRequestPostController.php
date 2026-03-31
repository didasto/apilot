<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\StorePostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\UpdatePostRequest;
use Didasto\Apilot\Tests\Fixtures\Resources\PostResource;

class SeparateRequestPostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $storeRequestClass = StorePostRequest::class;
    protected ?string $updateRequestClass = UpdatePostRequest::class;
    protected ?string $resourceClass = PostResource::class;
}
