<?php

declare(strict_types=1);

namespace Didasto\Apilot\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class OpenApiMeta
{
    public function __construct(
        public readonly ?string $tag = null,
        public readonly ?string $description = null,
        public readonly ?string $summary = null,
        public readonly bool $deprecated = false,
    ) {}
}
