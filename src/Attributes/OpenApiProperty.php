<?php

declare(strict_types=1);

namespace Didasto\Apilot\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OpenApiProperty
{
    /**
     * @param array<string, array{type?: string, format?: string, description?: string, example?: mixed, enum?: array<int, mixed>}> $properties
     */
    public function __construct(
        public readonly array $properties = [],
    ) {}
}
