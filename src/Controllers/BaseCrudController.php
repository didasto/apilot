<?php

declare(strict_types=1);

namespace Didasto\Apilot\Controllers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use LogicException;
use Didasto\Apilot\Controllers\Concerns\HasCrudHooks;
use Didasto\Apilot\Http\Resources\DefaultResource;

abstract class BaseCrudController extends Controller
{
    use HasCrudHooks;

    protected ?string $formRequestClass = null;

    protected ?string $resourceClass = null;

    protected function resolveFormRequest(): array
    {
        if ($this->formRequestClass === null) {
            return request()->all();
        }

        if (!class_exists($this->formRequestClass)) {
            throw new LogicException(
                sprintf('FormRequest class %s does not exist.', $this->formRequestClass)
            );
        }

        /** @var \Illuminate\Foundation\Http\FormRequest $formRequest */
        $formRequest = app($this->formRequestClass);

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
}
