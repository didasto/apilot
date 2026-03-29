<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature;

use Didasto\Apilot\Attributes\OpenApiMeta;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Controllers\ServiceCrudController;
use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\OpenApi\OpenApiGenerator;
use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Routing\CrudRouteRegistrar;
use Didasto\Apilot\Routing\RouteRegistry;
use Didasto\Apilot\Tests\Fixtures\Controllers\PostController;
use Didasto\Apilot\Tests\Fixtures\Controllers\TagController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\PostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\TagRequest;
use Didasto\Apilot\Tests\Fixtures\Resources\PostResource;
use Didasto\Apilot\Tests\Fixtures\Resources\TagResource;
use Didasto\Apilot\Tests\Fixtures\Services\TagService;
use Orchestra\Testbench\TestCase;

class OpenApiGeneratorTest extends TestCase
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
        $app['config']->set('apilot.openapi.info.title', 'Test API Documentation');
        $app['config']->set('apilot.openapi.info.version', '2.0.0');
        $app['config']->set('apilot.openapi.info.description', 'Test description');
        $app['config']->set('apilot.openapi.default_security', 'bearer');
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Registry vor jedem Test leeren
        $this->app->make(RouteRegistry::class)->clear();
    }

    protected function generator(): OpenApiGenerator
    {
        return $this->app->make(OpenApiGenerator::class);
    }

    protected function registerPostRoutes(array $middleware = [], array $actions = ['index', 'show', 'store', 'update', 'destroy']): void
    {
        $registrar = CrudRouteRegistrar::resource('posts', PostController::class);
        if (!empty($middleware)) {
            $registrar->middleware($middleware);
        }
        $registrar->only($actions)->register();
    }

    // =========================================================================
    // Tests
    // =========================================================================

    public function testGeneratesValidOpenApiStructure(): void
    {
        $spec = $this->generator()->generate();

        $this->assertArrayHasKey('openapi', $spec);
        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('components', $spec);
        $this->assertEquals('3.0.3', $spec['openapi']);
    }

    public function testInfoBlockMatchesConfig(): void
    {
        $spec = $this->generator()->generate();

        $this->assertEquals('Test API Documentation', $spec['info']['title']);
        $this->assertEquals('2.0.0', $spec['info']['version']);
        $this->assertEquals('Test description', $spec['info']['description']);
    }

    public function testPathsContainAllRegisteredRoutes(): void
    {
        $this->app->make(RouteRegistry::class)->clear();

        CrudRouteRegistrar::resource('posts', PostController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy'])
            ->register();

        CrudRouteRegistrar::resource('tags', TagController::class)
            ->only(['index', 'show'])
            ->register();

        $spec  = $this->generator()->generate();
        $paths = $spec['paths'];

        $this->assertArrayHasKey('/api/posts', $paths);
        $this->assertArrayHasKey('/api/posts/{id}', $paths);
        $this->assertArrayHasKey('/api/tags', $paths);
        $this->assertArrayHasKey('/api/tags/{id}', $paths);

        // Tags: nur GET (show), kein PUT/DELETE
        $this->assertArrayHasKey('get', $paths['/api/tags/{id}']);
        $this->assertArrayNotHasKey('put', $paths['/api/tags/{id}']);
        $this->assertArrayNotHasKey('delete', $paths['/api/tags/{id}']);
    }

    public function testIndexPathContainsPaginationParameters(): void
    {
        $this->registerPostRoutes();

        $spec       = $this->generator()->generate();
        $parameters = $spec['paths']['/api/posts']['get']['parameters'];
        $names      = array_column($parameters, 'name');

        $this->assertContains('page', $names);
        $this->assertContains('per_page', $names);
    }

    public function testIndexPathContainsSortParameter(): void
    {
        $this->registerPostRoutes();

        $spec       = $this->generator()->generate();
        $parameters = $spec['paths']['/api/posts']['get']['parameters'];

        $sortParam = null;
        foreach ($parameters as $p) {
            if ($p['name'] === 'sort') {
                $sortParam = $p;
                break;
            }
        }

        $this->assertNotNull($sortParam);
        $this->assertStringContainsString('title', $sortParam['description']);
        $this->assertStringContainsString('created_at', $sortParam['description']);
    }

    public function testIndexPathContainsFilterParameters(): void
    {
        $this->registerPostRoutes();

        $spec       = $this->generator()->generate();
        $parameters = $spec['paths']['/api/posts']['get']['parameters'];
        $names      = array_column($parameters, 'name');

        $this->assertContains('filter[status]', $names);
    }

    public function testStorePathContainsRequestBody(): void
    {
        $this->registerPostRoutes();

        $spec        = $this->generator()->generate();
        $storeOp     = $spec['paths']['/api/posts']['post'];

        $this->assertArrayHasKey('requestBody', $storeOp);
        $schema = $storeOp['requestBody']['content']['application/json']['schema'];
        $this->assertEquals('#/components/schemas/PostRequest', $schema['$ref']);
    }

    public function testShowPathContainsIdParameter(): void
    {
        $this->registerPostRoutes();

        $spec       = $this->generator()->generate();
        $parameters = $spec['paths']['/api/posts/{id}']['get']['parameters'];
        $names      = array_column($parameters, 'name');

        $this->assertContains('id', $names);

        $idParam = array_values(array_filter($parameters, fn ($p) => $p['name'] === 'id'))[0];
        $this->assertEquals('path', $idParam['in']);
        $this->assertTrue($idParam['required']);
    }

    public function testDestroyPathHas204And404Responses(): void
    {
        $this->registerPostRoutes();

        $spec     = $this->generator()->generate();
        $responses = $spec['paths']['/api/posts/{id}']['delete']['responses'];

        $this->assertArrayHasKey('204', $responses);
        $this->assertArrayHasKey('404', $responses);
    }

    public function testComponentSchemasContainRequestAndResponseSchemas(): void
    {
        $this->registerPostRoutes();

        $spec    = $this->generator()->generate();
        $schemas = $spec['components']['schemas'];

        $this->assertArrayHasKey('PostRequest', $schemas);
        $this->assertArrayHasKey('PostResponse', $schemas);
    }

    public function testRequestSchemaIsGeneratedFromFormRequestRules(): void
    {
        $this->registerPostRoutes();

        $spec    = $this->generator()->generate();
        $schema  = $spec['components']['schemas']['PostRequest'];

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('title', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['title']['type']);
        $this->assertEquals(255, $schema['properties']['title']['maxLength']);

        $this->assertArrayHasKey('body', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['body']['type']);
        $this->assertTrue($schema['properties']['body']['nullable']);

        $this->assertArrayHasKey('status', $schema['properties']);
        $this->assertArrayHasKey('enum', $schema['properties']['status']);
    }

    public function testRequiredFieldsAreCorrectInRequestSchema(): void
    {
        $this->registerPostRoutes();

        $spec   = $this->generator()->generate();
        $schema = $spec['components']['schemas']['PostRequest'];

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('title', $schema['required']);
        $this->assertNotContains('body', $schema['required']);
        $this->assertNotContains('status', $schema['required']);
    }

    public function testControllerWithoutFormRequestHasGenericRequestBody(): void
    {
        CrudRouteRegistrar::resource('items', NoFormRequestController::class)
            ->only(['store'])
            ->register();

        $spec    = $this->generator()->generate();
        $storeOp = $spec['paths']['/api/items']['post'];

        $this->assertArrayHasKey('requestBody', $storeOp);
        $schema = $storeOp['requestBody']['content']['application/json']['schema'];
        $this->assertArrayNotHasKey('$ref', $schema);
        $this->assertTrue($schema['additionalProperties']);
    }

    public function testSecurityIsAppliedForAuthMiddleware(): void
    {
        $this->registerPostRoutes(middleware: ['auth:sanctum']);

        $spec   = $this->generator()->generate();
        $indexOp = $spec['paths']['/api/posts']['get'];

        $this->assertArrayHasKey('security', $indexOp);
        $this->assertNotEmpty($indexOp['security']);
    }

    public function testSecurityIsNotAppliedWithoutAuthMiddleware(): void
    {
        $this->registerPostRoutes(middleware: []);

        $spec    = $this->generator()->generate();
        $indexOp = $spec['paths']['/api/posts']['get'];

        $this->assertArrayNotHasKey('security', $indexOp);
    }

    public function testCommonSchemasArePresent(): void
    {
        $spec    = $this->generator()->generate();
        $schemas = $spec['components']['schemas'];

        $this->assertArrayHasKey('PaginationMeta', $schemas);
        $this->assertArrayHasKey('PaginationLinks', $schemas);
        $this->assertArrayHasKey('ErrorResponse', $schemas);
        $this->assertArrayHasKey('ValidationErrorResponse', $schemas);
    }

    public function testOnlyActiveActionsGeneratePaths(): void
    {
        CrudRouteRegistrar::resource('posts', PostController::class)
            ->only(['index', 'show'])
            ->register();

        $spec  = $this->generator()->generate();
        $paths = $spec['paths'];

        $this->assertArrayHasKey('/api/posts', $paths);
        $this->assertArrayHasKey('get', $paths['/api/posts']);
        $this->assertArrayNotHasKey('post', $paths['/api/posts']);

        $this->assertArrayHasKey('/api/posts/{id}', $paths);
        $this->assertArrayHasKey('get', $paths['/api/posts/{id}']);
        $this->assertArrayNotHasKey('put', $paths['/api/posts/{id}']);
        $this->assertArrayNotHasKey('delete', $paths['/api/posts/{id}']);
    }

    public function testResourceNameConversion(): void
    {
        CrudRouteRegistrar::resource('blog-posts', BlogPostController::class)
            ->only(['index'])
            ->register();

        $spec    = $this->generator()->generate();
        $indexOp = $spec['paths']['/api/blog-posts']['get'];
        $schemas = $spec['components']['schemas'];

        $this->assertEquals(['BlogPosts'], $indexOp['tags']);
        $this->assertArrayHasKey('BlogPostRequest', $schemas);
        $this->assertArrayHasKey('BlogPostResponse', $schemas);
    }

    public function testOpenApiMetaAttributeOverridesTag(): void
    {
        CrudRouteRegistrar::resource('posts', CustomTagController::class)
            ->only(['index'])
            ->register();

        $spec    = $this->generator()->generate();
        $indexOp = $spec['paths']['/api/posts']['get'];

        $this->assertEquals(['Custom Tag'], $indexOp['tags']);
    }

    public function testOpenApiMetaDeprecatedFlag(): void
    {
        CrudRouteRegistrar::resource('posts', DeprecatedController::class)
            ->only(['index', 'show'])
            ->register();

        $spec  = $this->generator()->generate();
        $paths = $spec['paths'];

        $this->assertTrue($paths['/api/posts']['get']['deprecated']);
        $this->assertTrue($paths['/api/posts/{id}']['get']['deprecated']);
    }
}

// ---------------------------------------------------------------------------
// Fixture-Klassen (nur für diese Tests)
// ---------------------------------------------------------------------------

class NoFormRequestController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = null;
    protected ?string $resourceClass = null;
}

class BlogPostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    protected ?string $resourceClass = PostResource::class;
}

#[OpenApiMeta(tag: 'Custom Tag')]
class CustomTagController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = null;
    protected ?string $resourceClass = null;
}

#[OpenApiMeta(deprecated: true)]
class DeprecatedController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = null;
    protected ?string $resourceClass = null;
}
