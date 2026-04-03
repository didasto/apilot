<?php

declare(strict_types=1);

namespace Didasto\Apilot\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use LogicException;
use Didasto\Apilot\Exceptions\ActionNotAllowedException;
use Didasto\Apilot\Exceptions\ResourceNotFoundException;
use Didasto\Apilot\Traits\HasFiltering;
use Didasto\Apilot\Traits\HasPagination;
use Didasto\Apilot\Traits\HasSorting;

abstract class ModelCrudController extends BaseCrudController
{
    use HasPagination;
    use HasSorting;
    use HasFiltering;

    protected string $model;

    /** @var array<string, \Didasto\Apilot\Enums\AllowedFilter> */
    protected array $allowedFilters = [];

    /** @var array<int, string> */
    protected array $allowedSorts = [];

    protected ?int $defaultPerPage = null;

    public function index(Request $request): JsonResponse
    {
        $this->resolveAuthorization('index');
        $this->resolveModel();

        $query = ($this->model)::query();
        $query = $this->modifyIndexQuery($query, $request);
        $query = $this->applyFiltering($query, $request);
        $query = $this->applySorting($query, $request);

        $paginator = $this->applyPagination($query, $request);
        $paginator = $this->afterIndex($paginator, $request);

        $resourceClass = $this->resolveResourceClass();
        $mode = $this->resolveWrapperMode();

        // Use the collection pipeline only when a custom resource class is set
        // and we're in laravel mode. Otherwise build manually (required for field visibility).
        if ($mode === 'laravel' && $this->resourceClass !== null) {
            // Let Laravel's JsonResource handle response formatting.
            // transformResponse is still called (with empty data) so that hooks
            // implementing side-effects (logging, events) are not skipped.
            $this->transformResponse([], 'index', $request);

            return $resourceClass::collection($paginator)
                ->response()
                ->setStatusCode($this->getStatusCode('index'));
        }

        $items = collect($paginator->items())
            ->map(function (Model $model) use ($resourceClass, $request) {
                $filtered = $this->applyFieldVisibility($model->toArray(), $request);
                return $filtered ?? (new $resourceClass($model))->resolve($request);
            })
            ->all();

        $normalizedData = [
            'items' => $items,
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ];

        $normalizedData = $this->transformResponse($normalizedData, 'index', $request);

        if ($mode === 'none') {
            return new JsonResponse($normalizedData['items'] ?? [], $this->getStatusCode('index'));
        }

        $itemsKey = match ($mode) {
            'named' => $this->resolveWrapperKey(),
            default => 'data',
        };

        return new JsonResponse([
            $itemsKey => $normalizedData['items'] ?? [],
            'meta'    => $normalizedData['meta'] ?? [],
            'links'   => $normalizedData['links'] ?? [],
        ], $this->getStatusCode('index'));
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $this->resolveAuthorization('show');
        $this->resolveModel();

        $item = $this->findOrFail($id);
        $item = $this->afterShow($item, $request);

        $filteredData = $this->applyFieldVisibility($item->toArray(), $request);

        if ($filteredData !== null) {
            $resolved = $this->transformResponse($filteredData, 'show', $request);
            return $this->buildRawDataResponse($resolved, 'show');
        }

        $resourceClass = $this->resolveResourceClass();
        $resolved = (new $resourceClass($item))->resolve($request);
        $resolved = $this->transformResponse($resolved, 'show', $request);

        return $this->buildItemResponse($item, $resolved, $resourceClass, 'show', $request);
    }

    public function store(Request $request): JsonResponse
    {
        $this->resolveModel();

        $validated = $this->resolveFormRequest('store');
        $validated = $this->beforeStore($validated, $request);
        $item = ($this->model)::create($validated);
        $item = $this->afterStore($item, $request);

        $filteredData = $this->applyFieldVisibility($item->toArray(), $request);

        if ($filteredData !== null) {
            $resolved = $this->transformResponse($filteredData, 'store', $request);
            return $this->buildRawDataResponse($resolved, 'store');
        }

        $resourceClass = $this->resolveResourceClass();
        $resolved = (new $resourceClass($item))->resolve($request);
        $resolved = $this->transformResponse($resolved, 'store', $request);

        return $this->buildItemResponse($item, $resolved, $resourceClass, 'store', $request);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $this->resolveModel();

        $item = $this->findOrFail($id);
        $validated = $this->resolveFormRequest('update');
        $validated = $this->beforeUpdate($item, $validated, $request);
        $item->update($validated);
        $item = $this->afterUpdate($item, $request);

        $filteredData = $this->applyFieldVisibility($item->toArray(), $request);

        if ($filteredData !== null) {
            $resolved = $this->transformResponse($filteredData, 'update', $request);
            return $this->buildRawDataResponse($resolved, 'update');
        }

        $resourceClass = $this->resolveResourceClass();
        $resolved = (new $resourceClass($item))->resolve($request);
        $resolved = $this->transformResponse($resolved, 'update', $request);

        return $this->buildItemResponse($item, $resolved, $resourceClass, 'update', $request);
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $this->resolveAuthorization('destroy');
        $this->resolveModel();

        $item = $this->findOrFail($id);
        $allowed = $this->beforeDestroy($item, $request);

        if ($allowed === false) {
            throw new ActionNotAllowedException();
        }

        $item->delete();
        $this->afterDestroy($item, $request);

        return new JsonResponse(null, $this->getStatusCode('destroy'));
    }

    protected function resolveModel(): string
    {
        if (!isset($this->model) || $this->model === '') {
            throw new LogicException(
                sprintf('Property $model must be set in %s.', static::class)
            );
        }

        if (!class_exists($this->model)) {
            throw new LogicException(
                sprintf('Model class %s does not exist.', $this->model)
            );
        }

        return $this->model;
    }

    protected function findOrFail(int|string $id): Model
    {
        $model = ($this->model)::find($id);

        if ($model === null) {
            throw new ResourceNotFoundException();
        }

        return $model;
    }

    /** @deprecated Use resolveModel() instead */
    protected function ensureModelSet(): void
    {
        $this->resolveModel();
    }
}
