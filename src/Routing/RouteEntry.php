<?php

declare(strict_types=1);

namespace Didasto\Apilot\Routing;

readonly class RouteEntry
{
    /**
     * @param string             $resourceName    — z.B. 'posts'
     * @param string             $controllerClass — z.B. App\Http\Controllers\Api\PostController::class
     * @param array<int, string> $actions         — z.B. ['index', 'show', 'store', 'update', 'destroy']
     * @param array<int, string> $middleware       — z.B. ['auth:sanctum']
     * @param string             $prefix          — z.B. 'api' oder 'api/v1'
     */
    public function __construct(
        public string $resourceName,
        public string $controllerClass,
        public array $actions,
        public array $middleware,
        public string $prefix,
    ) {}
}
