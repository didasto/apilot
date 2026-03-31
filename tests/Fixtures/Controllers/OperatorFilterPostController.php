<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\Tests\Fixtures\Models\Post;

class OperatorFilterPostController extends ModelCrudController
{
    protected string $model = Post::class;

    protected array $allowedFilters = [
        'id'         => [AllowedFilter::EQUALS, AllowedFilter::NOT_EQUALS, AllowedFilter::IN, AllowedFilter::NOT_IN, AllowedFilter::GT, AllowedFilter::LT, AllowedFilter::GTE, AllowedFilter::LTE],
        'title'      => [AllowedFilter::EQUALS, AllowedFilter::LIKE, AllowedFilter::NOT_LIKE],
        'status'     => [AllowedFilter::EQUALS, AllowedFilter::NOT_EQUALS, AllowedFilter::IN, AllowedFilter::NOT_IN],
        'created_at' => [AllowedFilter::GT, AllowedFilter::LT, AllowedFilter::BETWEEN],
        'body'       => [AllowedFilter::IS_NULL, AllowedFilter::IS_NOT_NULL],
    ];
}
