<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Filters\BooleanFilter;
use Didasto\Apilot\Filters\DateFilter;
use Didasto\Apilot\Filters\IdFilter;
use Didasto\Apilot\Filters\NumericFilter;
use Didasto\Apilot\Filters\TextFilter;
use Didasto\Apilot\Tests\Fixtures\Filters\CustomStatusFilter;
use Didasto\Apilot\Tests\Fixtures\Models\Post;

class FilterSetPostController extends ModelCrudController
{
    protected string $model = Post::class;

    protected array $allowedFilters = [
        'id'         => IdFilter::class,
        'title'      => TextFilter::class,
        'price'      => NumericFilter::class,
        'created_at' => DateFilter::class,
        'is_active'  => BooleanFilter::class,
        'status'     => CustomStatusFilter::class,
    ];
}
