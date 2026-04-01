<?php

declare(strict_types=1);

namespace Didasto\Apilot\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Didasto\Apilot\Contracts\CrudServiceInterface;
use Didasto\Apilot\Dto\PaginatedResult;
use Didasto\Apilot\Dto\PaginationParams;
use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\Exceptions\ActionNotAllowedException;
use Didasto\Apilot\Exceptions\ResourceNotFoundException;
use Didasto\Apilot\Traits\HasFilterResolution;

abstract class ServiceCrudController extends BaseCrudController
{
    use HasFilterResolution;

    protected string $serviceClass;

    /** @var array<int, string>|array<string, AllowedFilter|array<int, AllowedFilter>|class-string> */
    protected array $allowedFilters = [];

    /** @var array<int, string> */
    protected array $allowedSorts = [];

    protected ?int $defaultPerPage = null;

    public function index(Request $request): JsonResponse
    {
        $service = $this->resolveService();

        $filters = $this->extractFilters($request);
        $sorting = $this->extractSorting($request);
        $pagination = $this->extractPaginationParams($request);

        $filters = $this->modifyIndexQuery($filters, $request);

        $result = $service->list($filters, $sorting, $pagination);
        $result = $this->afterIndex($result, $request);

        $normalizedData = $this->buildPaginatedResponse($result, $request);
        $normalizedData = $this->transformResponse($normalizedData, 'index', $request);

        $mode = $this->resolveWrapperMode();

        if ($mode === 'none') {
            return new JsonResponse($normalizedData['items'] ?? [], $this->getStatusCode('index'));
        }

        // ServiceCrudController always builds the response manually (no Eloquent paginator).
        // In 'laravel' mode we simulate Laravel's default by using 'data' as the items key.
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
        $service = $this->resolveService();

        $item = $service->find($id);

        if ($item === null) {
            throw new ResourceNotFoundException();
        }

        $item = $this->afterShow($item, $request);

        $resourceClass = $this->resolveResourceClass();
        $resolved = (new $resourceClass($item))->resolve($request);
        $resolved = $this->transformResponse($resolved, 'show', $request);

        return $this->buildItemResponse($item, $resolved, $resourceClass, 'show', $request);
    }

    public function store(Request $request): JsonResponse
    {
        $service = $this->resolveService();

        $validated = $this->resolveFormRequest('store');
        $validated = $this->beforeStore($validated, $request);
        $item = $service->create($validated);
        $item = $this->afterStore($item, $request);

        $resourceClass = $this->resolveResourceClass();
        $resolved = (new $resourceClass($item))->resolve($request);
        $resolved = $this->transformResponse($resolved, 'store', $request);

        return $this->buildItemResponse($item, $resolved, $resourceClass, 'store', $request);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $service = $this->resolveService();

        $existing = $service->find($id);

        if ($existing === null) {
            throw new ResourceNotFoundException();
        }

        $validated = $this->resolveFormRequest('update');
        $validated = $this->beforeUpdate($existing, $validated, $request);
        $item = $service->update($id, $validated);
        $item = $this->afterUpdate($item, $request);

        $resourceClass = $this->resolveResourceClass();
        $resolved = (new $resourceClass($item))->resolve($request);
        $resolved = $this->transformResponse($resolved, 'update', $request);

        return $this->buildItemResponse($item, $resolved, $resourceClass, 'update', $request);
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $service = $this->resolveService();

        $existing = $service->find($id);

        if ($existing === null) {
            throw new ResourceNotFoundException();
        }

        $allowed = $this->beforeDestroy($existing, $request);

        if ($allowed === false) {
            throw new ActionNotAllowedException();
        }

        $service->delete($id);
        $this->afterDestroy($existing, $request);

        return new JsonResponse(null, $this->getStatusCode('destroy'));
    }

    protected function resolveService(): CrudServiceInterface
    {
        if (!isset($this->serviceClass) || $this->serviceClass === '') {
            throw new LogicException('The $serviceClass property must be set in ' . static::class);
        }

        $service = app($this->serviceClass);

        if (!$service instanceof CrudServiceInterface) {
            throw new LogicException(
                sprintf('Class %s must implement %s', $this->serviceClass, CrudServiceInterface::class)
            );
        }

        return $service;
    }

    protected function extractFilters(Request $request): array
    {
        $filterInput = $request->input(config('apilot.filtering.param', 'filter'), []);

        if (!is_array($filterInput)) {
            return [];
        }

        // Legacy format: integer-indexed array ['name', 'slug']
        if (!empty($this->allowedFilters) && is_int(array_key_first($this->allowedFilters))) {
            return $this->extractFiltersLegacy($filterInput);
        }

        // New format: associative array with AllowedFilter/array/FilterSet
        $extracted = [];

        foreach ($filterInput as $field => $value) {
            if (!array_key_exists($field, $this->allowedFilters)) {
                continue;
            }

            $allowedOperators = $this->resolveAllowedOperators($field);

            if (is_array($value)) {
                foreach ($value as $operatorKey => $operatorValue) {
                    $operator = AllowedFilter::tryFrom((string) $operatorKey);
                    if ($operator !== null && in_array($operator, $allowedOperators, true)) {
                        $extracted[$field][$operator->value] = $operatorValue;
                    }
                }
            } else {
                if ($value !== '' && $value !== null) {
                    $defaultOperator = $this->resolveDefaultOperator($field, $allowedOperators);
                    $extracted[$field][$defaultOperator->value] = $value;
                }
            }
        }

        return $extracted;
    }

    private function extractFiltersLegacy(array $filterInput): array
    {
        $result = [];

        foreach ($filterInput as $field => $value) {
            if (!in_array($field, $this->allowedFilters, true)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $result[$field] = $value;
        }

        return $result;
    }

    protected function extractSorting(Request $request): array
    {
        $sortParam = config('apilot.sorting.param', 'sort');
        $sortValue = $request->input($sortParam);

        // Ignore array injection (e.g. ?sort[]=title)
        if (empty($sortValue) || !is_string($sortValue)) {
            return [];
        }

        $result = [];
        $fields = explode(',', $sortValue);

        foreach ($fields as $field) {
            $field = trim($field);

            if ($field === '') {
                continue;
            }

            $direction = 'asc';

            if (str_starts_with($field, '-')) {
                $direction = 'desc';
                $field = substr($field, 1);
            }

            if (in_array($field, $this->allowedSorts, true)) {
                $result[$field] = $direction;
            }
        }

        return $result;
    }

    protected function extractPaginationParams(Request $request): PaginationParams
    {
        $perPageParam = config('apilot.pagination.per_page_param', 'per_page');
        $defaultPerPage = $this->defaultPerPage ?? config('apilot.pagination.default_per_page', 15);
        $maxPerPage = config('apilot.pagination.max_per_page', 100);

        $rawPerPage = $request->input($perPageParam, $defaultPerPage);
        $perPage = is_numeric($rawPerPage) ? (int) $rawPerPage : (int) $defaultPerPage;
        $perPage = max(1, min($perPage, $maxPerPage));

        $rawPage = $request->input('page', 1);
        $page = is_numeric($rawPage) ? (int) $rawPage : 1;
        $page = max(1, $page);

        return new PaginationParams($page, $perPage);
    }

    /**
     * Builds the normalized data array (items, meta, links) from a PaginatedResult.
     * The actual response wrapping is handled by index() based on the wrapper mode.
     *
     * @return array{items: array<mixed>, meta: array<string, int>, links: array<string, string|null>}
     */
    protected function buildPaginatedResponse(PaginatedResult $result, Request $request): array
    {
        $resourceClass = $this->resolveResourceClass();
        $items = array_map(fn (mixed $item) => (new $resourceClass($item))->resolve($request), $result->items);

        $lastPage    = $result->lastPage();
        $currentPage = $result->currentPage;

        return [
            'items' => $items,
            'meta'  => [
                'current_page' => $currentPage,
                'last_page'    => $lastPage,
                'per_page'     => $result->perPage,
                'total'        => $result->total,
            ],
            'links' => [
                'first' => $request->fullUrlWithQuery(['page' => 1]),
                'last'  => $request->fullUrlWithQuery(['page' => $lastPage]),
                'prev'  => $currentPage > 1 ? $request->fullUrlWithQuery(['page' => $currentPage - 1]) : null,
                'next'  => $currentPage < $lastPage ? $request->fullUrlWithQuery(['page' => $currentPage + 1]) : null,
            ],
        ];
    }
}
