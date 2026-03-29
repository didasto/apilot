<?php

declare(strict_types=1);

namespace Didasto\Apilot\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DefaultResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource->toArray();
    }
}
