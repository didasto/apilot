<?php

declare(strict_types=1);

namespace Didasto\Apilot\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class ApiResource
{
    /**
     * @param string                  $path       — URI-Pfad der Resource (z.B. '/posts', '/api/v1/comments')
     * @param array<int, string>|null $only       — Nur diese Actions registrieren. null = alle.
     * @param array<int, string>|null $except     — Diese Actions ausschließen. null = keine.
     * @param string|null             $name       — Route-Name-Prefix (z.B. 'api.v1.posts'). null = auto-generiert.
     * @param array<int, string>      $middleware — Zusätzliche Middleware für diese Resource.
     */
    public function __construct(
        public readonly string $path,
        public readonly ?array $only = null,
        public readonly ?array $except = null,
        public readonly ?string $name = null,
        public readonly array $middleware = [],
    ) {}
}
