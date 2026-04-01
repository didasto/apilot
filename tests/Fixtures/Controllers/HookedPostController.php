<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Controllers;

use Illuminate\Http\Request;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\PostRequest;
use Didasto\Apilot\Tests\Fixtures\Resources\PostResource;

class HookedPostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    protected ?string $resourceClass = PostResource::class;
    protected array $allowedFilters = ['status' => AllowedFilter::EXACT];
    protected array $allowedSorts = ['title', 'created_at'];

    public static array $hooksCalled = [];
    public static mixed $lastTransformResponseData = null;

    public static function resetHooks(): void
    {
        static::$hooksCalled = [];
        static::$lastTransformResponseData = null;
    }

    protected function modifyIndexQuery(mixed $query, Request $request): mixed
    {
        static::$hooksCalled[] = 'modifyIndexQuery';

        return $query;
    }

    protected function beforeStore(array $data, Request $request): array
    {
        static::$hooksCalled[] = 'beforeStore';
        $data['status'] = $data['status'] ?? 'draft';

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

        if ($item->status === 'published') {
            return false;
        }

        return true;
    }

    protected function afterDestroy(mixed $item, Request $request): void
    {
        static::$hooksCalled[] = 'afterDestroy';
    }

    protected function transformResponse(mixed $data, string $action, Request $request): mixed
    {
        static::$hooksCalled[] = "transformResponse:{$action}";
        static::$lastTransformResponseData = $data;

        return $data;
    }
}
