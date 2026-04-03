<?php

declare(strict_types=1);

namespace Didasto\Apilot\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LogicException;
use Didasto\Apilot\Controllers\Concerns\HasCrudHooks;
use Didasto\Apilot\Http\Resources\DefaultResource;

abstract class BaseCrudController extends Controller
{
    use HasCrudHooks;

    protected ?string $formRequestClass = null;

    protected ?string $storeRequestClass = null;

    protected ?string $updateRequestClass = null;

    protected ?string $indexRequestClass = null;

    protected ?string $showRequestClass = null;

    protected ?string $destroyRequestClass = null;

    protected ?string $resourceClass = null;

    /**
     * Whitelist: Only show these fields in the response.
     * Ignored when $resourceClass is set.
     * Can be overridden by the visibleFields() method.
     *
     * @var array<int, string>
     */
    protected array $visibleFields = [];

    /**
     * Blacklist: Remove these fields from the response.
     * Applied AFTER whitelist — blacklist always wins.
     * Ignored when $resourceClass is set.
     * Can be overridden by the hiddenFields() method.
     *
     * @var array<int, string>
     */
    protected array $hiddenFields = [];

    protected function resolveFormRequest(string $action = ''): array
    {
        $requestClass = match ($action) {
            'store'  => $this->storeRequestClass ?? $this->formRequestClass,
            'update' => $this->updateRequestClass ?? $this->formRequestClass,
            default  => $this->formRequestClass,
        };

        if ($requestClass === null) {
            return request()->all();
        }

        if (!class_exists($requestClass)) {
            throw new LogicException(
                sprintf('FormRequest class %s does not exist.', $requestClass)
            );
        }

        /** @var \Illuminate\Foundation\Http\FormRequest $formRequest */
        $formRequest = app($requestClass);

        return $formRequest->validated();
    }

    /**
     * Resolves the FormRequest for authorization-only actions (index, show, destroy).
     * No fallback to $formRequestClass — only the action-specific class is used.
     * Throws if authorize() returns false (403).
     */
    protected function resolveAuthorization(string $action): void
    {
        $requestClass = match ($action) {
            'index'   => $this->indexRequestClass,
            'show'    => $this->showRequestClass,
            'destroy' => $this->destroyRequestClass,
            default   => null,
        };

        if ($requestClass === null) {
            return;
        }

        if (!class_exists($requestClass)) {
            throw new LogicException(
                sprintf('FormRequest class %s does not exist.', $requestClass)
            );
        }

        app($requestClass);
    }

    /**
     * Dynamic whitelist — override in subclass for request-based logic.
     *
     * @return array<int, string>
     */
    protected function visibleFields(Request $request): array
    {
        return $this->visibleFields;
    }

    /**
     * Dynamic blacklist — override in subclass for request-based logic.
     *
     * @return array<int, string>
     */
    protected function hiddenFields(Request $request): array
    {
        return $this->hiddenFields;
    }

    /**
     * Applies whitelist and blacklist field visibility to the given data array.
     * Returns null when $resourceClass is set (resource handles field selection).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    protected function applyFieldVisibility(array $data, Request $request): ?array
    {
        if ($this->resourceClass !== null) {
            return null;
        }

        $visible = $this->visibleFields($request);
        if (!empty($visible)) {
            $data = array_intersect_key($data, array_flip($visible));
        }

        $hidden = $this->hiddenFields($request);
        if (!empty($hidden)) {
            $data = array_diff_key($data, array_flip($hidden));
        }

        return $data;
    }

    /**
     * Converts a mixed item to an array for field visibility processing.
     *
     * @return array<string, mixed>
     */
    protected function itemToArray(mixed $item): array
    {
        if (is_array($item)) {
            return $item;
        }

        if (method_exists($item, 'toArray')) {
            return $item->toArray();
        }

        if ($item instanceof \stdClass) {
            return (array) $item;
        }

        return [];
    }

    protected function resolveResourceClass(): string
    {
        if ($this->resourceClass !== null) {
            if (!class_exists($this->resourceClass)) {
                throw new LogicException(
                    sprintf('Resource class %s does not exist.', $this->resourceClass)
                );
            }
        }

        return $this->resourceClass ?? DefaultResource::class;
    }

    /**
     * Determines the wrapper mode from config.
     *
     * Returns:
     *   'laravel' — null config: let Laravel's JsonResource handle formatting
     *   'none'    — [] config: no wrapper, paginated responses use 'items' key
     *   'named'   — string config: wrap under the given key name
     */
    protected function resolveWrapperMode(): string
    {
        $wrapper = config('apilot.response_wrapper');

        if ($wrapper === null) {
            return 'laravel';
        }

        if (is_array($wrapper) && empty($wrapper)) {
            return 'none';
        }

        if (is_string($wrapper) && $wrapper !== '') {
            return 'named';
        }

        // Fallback for invalid values (empty string, non-empty array, integer, etc.)
        return 'laravel';
    }

    /**
     * Returns the configured wrapper key for 'named' mode, or null otherwise.
     */
    protected function resolveWrapperKey(): ?string
    {
        $wrapper = config('apilot.response_wrapper');

        if (is_string($wrapper) && $wrapper !== '') {
            return $wrapper;
        }

        return null;
    }

    /**
     * Builds a JsonResponse for a single item (show, store, update).
     *
     * Must be called AFTER transformResponse() so that $resolved already
     * contains the hook-modified data.
     *
     * In 'laravel' mode the $resolved data is not used — Laravel's native
     * resource response pipeline handles formatting (including the default
     * "data" wrapper). In 'none' and 'named' modes $resolved is placed
     * directly into the manually-built JSON response.
     */
    protected function buildItemResponse(
        mixed  $item,
        mixed  $resolved,
        string $resourceClass,
        string $action,
        Request $request,
    ): JsonResponse {
        $mode = $this->resolveWrapperMode();

        if ($mode === 'laravel') {
            return (new $resourceClass($item))
                ->response()
                ->setStatusCode($this->getStatusCode($action));
        }

        $responseData = match ($mode) {
            'none'  => $resolved,
            'named' => [$this->resolveWrapperKey() => $resolved],
            default => $resolved,
        };

        return new JsonResponse($responseData, $this->getStatusCode($action));
    }

    /**
     * Builds a JsonResponse for raw array data (used when field visibility is active).
     * In 'laravel' mode, wraps under 'data' to match the standard Laravel resource format.
     */
    protected function buildRawDataResponse(mixed $resolved, string $action): JsonResponse
    {
        $mode = $this->resolveWrapperMode();

        $responseData = match ($mode) {
            'none'  => $resolved,
            'named' => [$this->resolveWrapperKey() => $resolved],
            default => ['data' => $resolved],
        };

        return new JsonResponse($responseData, $this->getStatusCode($action));
    }
}
