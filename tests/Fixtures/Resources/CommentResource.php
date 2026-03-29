<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->resource->id ?? null,
            'body'       => $this->resource->body ?? null,
            'author'     => $this->resource->author ?? null,
            'created_at' => $this->resource->created_at ?? null,
        ];
    }
}
