<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->resource->id,
            'title'      => $this->resource->title,
            'body'       => $this->resource->body,
            'status'     => $this->resource->status,
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
