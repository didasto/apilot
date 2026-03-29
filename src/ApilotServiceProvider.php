<?php

declare(strict_types=1);

namespace Didasto\Apilot;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Didasto\Apilot\Console\GenerateOpenApiSpecCommand;
use Didasto\Apilot\Http\Controllers\OpenApiDocController;
use Didasto\Apilot\Http\Middleware\ForceJsonResponse;
use Didasto\Apilot\OpenApi\InfoBuilder;
use Didasto\Apilot\OpenApi\OpenApiGenerator;
use Didasto\Apilot\OpenApi\PathBuilder;
use Didasto\Apilot\OpenApi\SchemaBuilder;
use Didasto\Apilot\OpenApi\SpecValidator;
use Didasto\Apilot\Routing\CrudRouteRegistrar;
use Didasto\Apilot\Routing\RouteRegistry;

class ApilotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/apilot.php',
            'apilot'
        );

        $this->app->singleton(CrudRouteRegistrar::class, function () {
            return new CrudRouteRegistrar();
        });

        $this->app->singleton(RouteRegistry::class, function () {
            return new RouteRegistry();
        });

        $this->app->singleton(SchemaBuilder::class, function () {
            return new SchemaBuilder();
        });

        $this->app->singleton(PathBuilder::class, function ($app) {
            return new PathBuilder($app->make(SchemaBuilder::class));
        });

        $this->app->singleton(InfoBuilder::class, function () {
            return new InfoBuilder();
        });

        $this->app->singleton(OpenApiGenerator::class, function ($app) {
            return new OpenApiGenerator(
                $app->make(RouteRegistry::class),
                $app->make(SchemaBuilder::class),
                $app->make(PathBuilder::class),
                $app->make(InfoBuilder::class),
            );
        });

        $this->app->singleton(SpecValidator::class, function () {
            return new SpecValidator();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/apilot.php' => config_path('apilot.php'),
            ], 'apilot');

            $this->commands([
                GenerateOpenApiSpecCommand::class,
            ]);
        }

        $this->app['router']->aliasMiddleware('apilot.json', ForceJsonResponse::class);

        if (config('apilot.openapi.enabled', true)) {
            Route::prefix(config('apilot.prefix', 'api'))
                ->middleware(config('apilot.openapi.middleware', ['api']))
                ->get(config('apilot.openapi.path', 'doc'), OpenApiDocController::class)
                ->name('apilot.doc');
        }
    }
}
