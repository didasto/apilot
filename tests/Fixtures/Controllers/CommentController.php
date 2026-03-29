<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\CommentRequest;
use Didasto\Apilot\Tests\Fixtures\Resources\CommentResource;

class CommentController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = CommentRequest::class;
    protected ?string $resourceClass = CommentResource::class;

    protected array $allowedSorts = ['created_at'];
}
