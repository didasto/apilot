<?php

declare(strict_types=1);

namespace Didasto\Apilot\Routing;

use Didasto\Apilot\Attributes\ApiResource;
use Illuminate\Support\Facades\Route;

class AttributeRouteRegistrar
{
    /** @var array<int, string> */
    protected static array $allActions = ['index', 'show', 'store', 'update', 'destroy'];

    public function __construct(
        private readonly RouteRegistry $registry,
    ) {}

    /**
     * Scannt die angegebenen Controller-Klassen nach #[ApiResource] Attributen
     * und registriert die Routen.
     *
     * @param array<int, string> $controllerClasses — Vollqualifizierte Klassennamen
     */
    public function register(array $controllerClasses): void
    {
        foreach ($controllerClasses as $controllerClass) {
            $this->registerController($controllerClass);
        }
    }

    /**
     * Scannt ein Verzeichnis nach Controller-Klassen mit #[ApiResource] Attribut.
     *
     * @param string $directory — Absoluter Pfad zum Verzeichnis
     * @param string $namespace — Basis-Namespace (z.B. 'App\\Http\\Controllers\\Api')
     */
    public function registerDirectory(string $directory, string $namespace): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace(
                rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
                '',
                $file->getPathname(),
            );
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
            $className    = $namespace . '\\' . substr($relativePath, 0, -4); // strip .php

            if (!class_exists($className)) {
                continue;
            }

            $this->registerController($className);
        }
    }

    protected function registerController(string $controllerClass): void
    {
        try {
            $reflection = new \ReflectionClass($controllerClass);
        } catch (\Throwable) {
            return;
        }

        $attributes = $reflection->getAttributes(ApiResource::class);

        if (empty($attributes)) {
            return;
        }

        $apiResource = $attributes[0]->newInstance();

        $actions = $this->resolveActions($apiResource);

        // Resource-Name aus dem Pfad ableiten (letztes Segment)
        $resourceName = trim(basename($apiResource->path), '/');

        // Route-Name-Prefix: aus Attribut oder auto-generiert
        $routeName = $apiResource->name ?? $resourceName;

        // Prefix: Alles vor dem letzten Segment, oder globaler Config-Prefix
        $pathSegments = explode('/', trim($apiResource->path, '/'));
        if (count($pathSegments) > 1) {
            $prefix = implode('/', array_slice($pathSegments, 0, -1));
        } else {
            $prefix = config('apilot.prefix', 'api');
        }

        // Middleware: Globale Config-Middleware + per-Resource-Middleware
        $middleware = array_merge(
            config('apilot.middleware', ['api']),
            $apiResource->middleware,
        );

        // In der RouteRegistry registrieren
        try {
            $this->registry->register(new RouteEntry(
                resourceName:    $resourceName,
                controllerClass: $controllerClass,
                actions:         $actions,
                middleware:      $middleware,
                prefix:          $prefix,
            ));
        } catch (\Throwable) {
        }

        // Routen registrieren
        Route::prefix($prefix)
            ->middleware($middleware)
            ->group(function () use ($controllerClass, $resourceName, $actions, $routeName): void {
                foreach ($actions as $action) {
                    match ($action) {
                        'index'   => Route::get($resourceName, [$controllerClass, 'index'])
                                         ->name("{$routeName}.index"),
                        'show'    => Route::get("{$resourceName}/{id}", [$controllerClass, 'show'])
                                         ->name("{$routeName}.show"),
                        'store'   => Route::post($resourceName, [$controllerClass, 'store'])
                                          ->name("{$routeName}.store"),
                        'update'  => Route::put("{$resourceName}/{id}", [$controllerClass, 'update'])
                                          ->name("{$routeName}.update"),
                        'destroy' => Route::delete("{$resourceName}/{id}", [$controllerClass, 'destroy'])
                                          ->name("{$routeName}.destroy"),
                        default   => null,
                    };
                }
            });
    }

    /** @return array<int, string> */
    protected function resolveActions(ApiResource $apiResource): array
    {
        $actions = self::$allActions;

        if ($apiResource->only !== null) {
            $actions = array_values(array_intersect($actions, $apiResource->only));
        } elseif ($apiResource->except !== null) {
            $actions = array_values(array_diff($actions, $apiResource->except));
        }

        return $actions;
    }
}
