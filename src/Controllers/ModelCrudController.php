<?php

declare(strict_types=1);

namespace Didasto\Apilot\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
        $this->resolveModel();

        $query = ($this->model)::query();
        $query = $this->modifyIndexQuery($query, $request);
        $query = $this->applyFiltering($query, $request);
        $query = $this->applySorting($query, $request);

        $paginator = $this->applyPagination($query, $request);
        $paginator = $this->afterIndex($paginator, $request);

        $resourceClass = $this->resolveResourceClass();
        $items = collect($paginator->items())->map(fn (Model $model) => (new $resourceClass($model))->resolve());

        $data = [
            'data'  => $items,
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

        $data = $this->transformResponse($data, 'index', $request);

        return new JsonResponse($data, $this->getStatusCode('index'));
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $this->resolveModel();

        $item = $this->findOrFail($id);
        $item = $this->afterShow($item, $request);

        $resourceClass = $this->resolveResourceClass();
        $data = new $resourceClass($item);
        $data = $this->transformResponse($data, 'show', $request);

        return $this->toJsonResponse($data, $this->getStatusCode('show'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->resolveModel();

        $validated = $this->resolveFormRequest('store');
        $validated = $this->beforeStore($validated, $request);
        $item = ($this->model)::create($validated);
        $item = $this->afterStore($item, $request);

        $resourceClass = $this->resolveResourceClass();
        $data = new $resourceClass($item);
        $data = $this->transformResponse($data, 'store', $request);

        return $this->toJsonResponse($data, $this->getStatusCode('store'));
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $this->resolveModel();

        $item = $this->findOrFail($id);
        $validated = $this->resolveFormRequest('update');
        $validated = $this->beforeUpdate($item, $validated, $request);
        $item->update($validated);
        $item = $this->afterUpdate($item, $request);

        $resourceClass = $this->resolveResourceClass();
        $data = new $resourceClass($item);
        $data = $this->transformResponse($data, 'update', $request);

        return $this->toJsonResponse($data, $this->getStatusCode('update'));
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
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

    protected function toJsonResponse(mixed $data, int $status): JsonResponse
    {
        if ($data instanceof JsonResource) {
            return $data->response()->setStatusCode($status);
        }

        return new JsonResponse($data, $status);
    }

    /** @deprecated Use resolveModel() instead */
    protected function ensureModelSet(): void
    {
        $this->resolveModel();
    }
}
