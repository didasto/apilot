<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature;

use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\OpenApi\OpenApiGenerator;
use Didasto\Apilot\Routing\AttributeRouteRegistrar;
use Didasto\Apilot\Routing\CrudRouteRegistrar;
use Didasto\Apilot\Routing\RouteRegistry;
use Didasto\Apilot\Tests\Fixtures\Controllers\AttributeOnlyShowController;
use Didasto\Apilot\Tests\Fixtures\Controllers\AttributePostController;
use Didasto\Apilot\Tests\Fixtures\Controllers\AttributeTagController;
use Didasto\Apilot\Tests\Fixtures\Controllers\TagController;
use Orchestra\Testbench\TestCase;

class AttributeRouteRegistrarTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ApilotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('apilot.prefix', 'api');
        $app['config']->set('apilot.middleware', ['api']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(RouteRegistry::class)->clear();
    }

    protected function registrar(): AttributeRouteRegistrar
    {
        return $this->app->make(AttributeRouteRegistrar::class);
    }

    protected function generator(): OpenApiGenerator
    {
        return $this->app->make(OpenApiGenerator::class);
    }

    protected function routeNames(): array
    {
        return collect($this->app['router']->getRoutes())->map(fn ($r) => $r->getName())->filter()->values()->all();
    }

    protected function routeUris(): array
    {
        return collect($this->app['router']->getRoutes())->map(fn ($r) => $r->uri())->values()->all();
    }

    // =========================================================================
    // Tests
    // =========================================================================

    public function testAttributeRegistersAllCrudRoutes(): void
    {
        $this->registrar()->register([AttributePostController::class]);

        $names = $this->routeNames();
        $this->assertContains('posts.index', $names);
        $this->assertContains('posts.show', $names);
        $this->assertContains('posts.store', $names);
        $this->assertContains('posts.update', $names);
        $this->assertContains('posts.destroy', $names);
    }

    public function testAttributeRespectsOnlyParameter(): void
    {
        $this->registrar()->register([AttributeTagController::class]);

        $names = $this->routeNames();
        $this->assertContains('tags.index', $names);
        $this->assertContains('tags.show', $names);
        $this->assertNotContains('tags.store', $names);
        $this->assertNotContains('tags.update', $names);
        $this->assertNotContains('tags.destroy', $names);
    }

    public function testAttributeRespectsExceptParameter(): void
    {
        // Inline-Controller mit except
        $controllerClass = new class {
            public static function getClass(): string
            {
                return ExceptDestroyController::class;
            }
        };

        $this->registrar()->register([ExceptDestroyController::class]);

        $names = $this->routeNames();
        $this->assertContains('except-posts.index', $names);
        $this->assertContains('except-posts.store', $names);
        $this->assertNotContains('except-posts.destroy', $names);
    }

    public function testAttributeAppliesMiddleware(): void
    {
        $this->registrar()->register([MiddlewareTestController::class]);

        $routes = collect($this->app['router']->getRoutes())->filter(
            fn ($r) => str_contains($r->getName() ?? '', 'middleware-posts')
        )->values()->all();

        $this->assertNotEmpty($routes);
        $route = $routes[0];
        $this->assertContains('auth:sanctum', $route->middleware());
    }

    public function testAttributeSetsRouteName(): void
    {
        $this->registrar()->register([CustomNameController::class]);

        $names = $this->routeNames();
        $this->assertContains('api.v1.posts.index', $names);
        $this->assertContains('api.v1.posts.show', $names);
    }

    public function testAttributeAutoGeneratesRouteName(): void
    {
        $this->registrar()->register([AttributePostController::class]);

        $names = $this->routeNames();
        $this->assertContains('posts.index', $names);
        $this->assertContains('posts.show', $names);
    }

    public function testAttributeWithCustomPrefix(): void
    {
        $this->registrar()->register([CustomPrefixController::class]);

        $uris = $this->routeUris();
        $this->assertContains('api/v2/posts', $uris);
        $this->assertContains('api/v2/posts/{id}', $uris);
    }

    public function testAttributeWithSimplePath(): void
    {
        $this->registrar()->register([AttributeTagController::class]);

        $uris = $this->routeUris();
        $this->assertContains('api/tags', $uris);
        $this->assertContains('api/tags/{id}', $uris);
    }

    public function testAttributeRoutesAppearInRouteRegistry(): void
    {
        $this->registrar()->register([AttributePostController::class]);

        $entries = $this->app->make(RouteRegistry::class)->all();
        $this->assertNotEmpty($entries);

        $entry = $entries[0];
        $this->assertEquals('posts', $entry->resourceName);
        $this->assertEquals(AttributePostController::class, $entry->controllerClass);
    }

    public function testAttributeRoutesAppearInOpenApiSpec(): void
    {
        $this->registrar()->register([AttributePostController::class]);

        $spec  = $this->generator()->generate();
        $paths = $spec['paths'];

        $this->assertArrayHasKey('/api/posts', $paths);
        $this->assertArrayHasKey('/api/posts/{id}', $paths);
    }

    public function testManualAndAttributeRegistrationWorkTogether(): void
    {
        // PostController via Attribut
        $this->registrar()->register([AttributePostController::class]);

        // TagController via CrudRouteRegistrar
        CrudRouteRegistrar::resource('comments', TagController::class)
            ->only(['index'])
            ->register();

        $names = $this->routeNames();
        $this->assertContains('posts.index', $names);
        $this->assertContains('comments.index', $names);

        $registry = $this->app->make(RouteRegistry::class);
        $this->assertCount(2, $registry->all());
    }

    public function testRegisterDirectoryFindsAnnotatedControllers(): void
    {
        $directory = dirname(__DIR__) . '/Fixtures/Controllers';
        $namespace = 'Didasto\\Apilot\\Tests\\Fixtures\\Controllers';

        $this->registrar()->registerDirectory($directory, $namespace);

        $names = $this->routeNames();
        // AttributePostController hat #[ApiResource] → registriert
        $this->assertContains('posts.index', $names);
    }

    public function testRegisterDirectoryIgnoresNonAnnotatedControllers(): void
    {
        $directory = dirname(__DIR__) . '/Fixtures/Controllers';
        $namespace = 'Didasto\\Apilot\\Tests\\Fixtures\\Controllers';

        $registryBefore = count($this->app->make(RouteRegistry::class)->all());

        $this->registrar()->registerDirectory($directory, $namespace);

        // Nur Controller mit #[ApiResource] werden registriert
        $entries = $this->app->make(RouteRegistry::class)->all();
        foreach ($entries as $entry) {
            // Alle registrierten Controller müssen das ApiResource-Attribut haben
            $reflection = new \ReflectionClass($entry->controllerClass);
            $this->assertNotEmpty($reflection->getAttributes(\Didasto\Apilot\Attributes\ApiResource::class));
        }
    }

    public function testControllerWithOnlyShowAttributeRegistersOnlyShowRoute(): void
    {
        $this->registrar()->register([AttributeOnlyShowController::class]);

        $names = $this->routeNames();
        $this->assertContains('items.show', $names);
        $this->assertNotContains('items.index', $names);
        $this->assertNotContains('items.store', $names);
        $this->assertNotContains('items.update', $names);
        $this->assertNotContains('items.destroy', $names);
    }
}

// ---------------------------------------------------------------------------
// Inline-Controller-Fixtures für diesen Test
// ---------------------------------------------------------------------------

use Didasto\Apilot\Attributes\ApiResource;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;

#[ApiResource(path: '/except-posts', except: ['destroy'])]
class ExceptDestroyController extends ModelCrudController
{
    protected string $model = Post::class;
}

#[ApiResource(path: '/middleware-posts', middleware: ['auth:sanctum'])]
class MiddlewareTestController extends ModelCrudController
{
    protected string $model = Post::class;
}

#[ApiResource(path: '/posts', name: 'api.v1.posts')]
class CustomNameController extends ModelCrudController
{
    protected string $model = Post::class;
}

#[ApiResource(path: '/api/v2/posts')]
class CustomPrefixController extends ModelCrudController
{
    protected string $model = Post::class;
}
