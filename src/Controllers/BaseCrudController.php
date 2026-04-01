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

    protected ?string $resourceClass = null;

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
}
