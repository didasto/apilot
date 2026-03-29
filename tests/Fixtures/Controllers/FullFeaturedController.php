<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Illuminate\Http\Request;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\PostRequest;
use Didasto\Apilot\Tests\Fixtures\Resources\PostResource;

class FullFeaturedController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    protected ?string $resourceClass = PostResource::class;

    protected array $allowedFilters = [
        'status' => AllowedFilter::EXACT,
        'title'  => AllowedFilter::PARTIAL,
    ];

    protected array $allowedSorts = ['title', 'created_at', 'status'];
    protected ?int $defaultPerPage = 10;

    public static array $hooksCalled = [];

    public static function resetHooks(): void
    {
        static::$hooksCalled = [];
    }

    protected function modifyIndexQuery(mixed $query, Request $request): mixed
    {
        static::$hooksCalled[] = 'modifyIndexQuery';

        return $query;
    }

    protected function afterIndex(mixed $result, Request $request): mixed
    {
        static::$hooksCalled[] = 'afterIndex';

        return $result;
    }

    protected function afterShow(mixed $item, Request $request): mixed
    {
        static::$hooksCalled[] = 'afterShow';

        return $item;
    }

    protected function beforeStore(array $data, Request $request): array
    {
        static::$hooksCalled[] = 'beforeStore';

        return $data;
    }

    protected function afterStore(mixed $item, Request $request): mixed
    {
        static::$hooksCalled[] = 'afterStore';

        return $item;
    }

    protected function beforeUpdate(mixed $item, array $data, Request $request): array
    {
        static::$hooksCalled[] = 'beforeUpdate';

        return $data;
    }

    protected function afterUpdate(mixed $item, Request $request): mixed
    {
        static::$hooksCalled[] = 'afterUpdate';

        return $item;
    }

    protected function beforeDestroy(mixed $item, Request $request): bool
    {
        static::$hooksCalled[] = 'beforeDestroy';

        return true;
    }

    protected function afterDestroy(mixed $item, Request $request): void
    {
        static::$hooksCalled[] = 'afterDestroy';
    }

    protected function transformResponse(mixed $data, string $action, Request $request): mixed
    {
        static::$hooksCalled[] = "transformResponse:{$action}";

        return $data;
    }
}
