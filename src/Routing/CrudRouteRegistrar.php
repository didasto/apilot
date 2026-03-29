<?php

declare(strict_types=1);

namespace Didasto\Apilot\Routing;

use Illuminate\Support\Facades\Route;

class CrudRouteRegistrar
{
    protected string $resource;

    protected string $controller;

    /** @var array<int, string> */
    protected array $only = [];

    /** @var array<int, string> */
    protected array $except = [];

    /** @var array<int, string> */
    protected array $extraMiddleware = [];

    protected bool $registered = false;

    /** @var array<int, string> */
    protected static array $allActions = ['index', 'show', 'store', 'update', 'destroy'];

    protected function __construct(string $resource, string $controller)
    {
        $this->resource = $resource;
        $this->controller = $controller;
    }

    public static function resource(string $resource, string $controller): static
    {
        return new static($resource, $controller);
    }

    public function only(array $actions): static
    {
        $this->only = $actions;

        return $this;
    }

    public function except(array $actions): static
    {
        $this->except = $actions;

        return $this;
    }

    public function middleware(array $middleware): static
    {
        $this->extraMiddleware = $middleware;

        return $this;
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        $prefix           = config('apilot.prefix', 'api');
        $globalMiddleware = config('apilot.middleware', ['api']);
        $middleware       = array_merge($globalMiddleware, $this->extraMiddleware);

        $actions    = $this->resolveActions();
        $resource   = $this->resource;
        $controller = $this->controller;

        // RouteRegistry mit den tatsächlich aktiven Actions befüllen
        try {
            $registry = app(RouteRegistry::class);
            $registry->register(new RouteEntry(
                resourceName:    $resource,
                controllerClass: $controller,
                actions:         $actions,
                middleware:      $middleware,
                prefix:          $prefix,
            ));
        } catch (\Throwable) {
            // Graceful degradation: Registry nicht verfügbar (z.B. in Unit-Tests ohne App-Container)
        }

        Route::prefix($prefix)
            ->middleware($middleware)
            ->group(function () use ($actions, $resource, $controller): void {
                foreach ($actions as $action) {
                    match ($action) {
                        'index'   => Route::get($resource, [$controller, 'index'])
                                         ->name("{$resource}.index"),
                        'show'    => Route::get("{$resource}/{id}", [$controller, 'show'])
                                         ->name("{$resource}.show"),
                        'store'   => Route::post($resource, [$controller, 'store'])
                                          ->name("{$resource}.store"),
                        'update'  => Route::put("{$resource}/{id}", [$controller, 'update'])
                                          ->name("{$resource}.update"),
                        'destroy' => Route::delete("{$resource}/{id}", [$controller, 'destroy'])
                                          ->name("{$resource}.destroy"),
                        default   => null,
                    };
                }
            });
    }

    public function __destruct()
    {
        $this->register();
    }

    /** @return array<int, string> */
    protected function resolveActions(): array
    {
        $actions = self::$allActions;

        if (!empty($this->only)) {
            $actions = array_intersect($actions, $this->only);
        }

        if (!empty($this->except)) {
            $actions = array_diff($actions, $this->except);
        }

        return array_values($actions);
    }
}
