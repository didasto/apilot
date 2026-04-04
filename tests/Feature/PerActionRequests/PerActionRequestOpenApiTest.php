<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\PerActionRequests;

use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\OpenApi\OpenApiGenerator;
use Didasto\Apilot\Routing\CrudRouteRegistrar;
use Didasto\Apilot\Routing\RouteRegistry;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\AdminOnlyRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\DestroyPostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\IndexPostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\ShowPostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\StorePostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\UpdatePostRequest;
use Orchestra\Testbench\TestCase;

class PerActionRequestOpenApiTest extends TestCase
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
    }

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require __DIR__ . '/../../Fixtures/Migrations/create_posts_table.php';
        $migration->up();

        $this->app->make(RouteRegistry::class)->clear();
    }

    protected function generator(): OpenApiGenerator
    {
        return $this->app->make(OpenApiGenerator::class);
    }

    public function testIndexRequestClassDoesNotGenerateRequestBodySchema(): void
    {
        CrudRouteRegistrar::resource('openapi-idx-posts', OpenApiIndexOnlyController::class)->register();

        $spec = $this->generator()->generate();
        $indexOp = $spec['paths']['/api/openapi-idx-posts']['get'] ?? null;

        $this->assertNotNull($indexOp);
        $this->assertArrayNotHasKey('requestBody', $indexOp);
    }

    public function testShowRequestClassDoesNotGenerateRequestBodySchema(): void
    {
        CrudRouteRegistrar::resource('openapi-show-posts', OpenApiShowOnlyController::class)
            ->only(['show'])
            ->register();

        $spec = $this->generator()->generate();
        $showOp = $spec['paths']['/api/openapi-show-posts/{id}']['get'] ?? null;

        $this->assertNotNull($showOp);
        $this->assertArrayNotHasKey('requestBody', $showOp);
    }

    public function testDestroyRequestClassDoesNotGenerateRequestBodySchema(): void
    {
        CrudRouteRegistrar::resource('openapi-del-posts', OpenApiDestroyOnlyController::class)
            ->only(['destroy'])
            ->register();

        $spec = $this->generator()->generate();
        $destroyOp = $spec['paths']['/api/openapi-del-posts/{id}']['delete'] ?? null;

        $this->assertNotNull($destroyOp);
        $this->assertArrayNotHasKey('requestBody', $destroyOp);
    }

    public function testStoreAndUpdateRequestClassesGenerateSeparateSchemas(): void
    {
        CrudRouteRegistrar::resource('openapi-sep-posts', OpenApiSeparateRequestsController::class)
            ->only(['store', 'update'])
            ->register();

        $spec = $this->generator()->generate();
        $schemas = $spec['components']['schemas'];

        $this->assertArrayHasKey('OpenapiSepPostStoreRequest', $schemas);
        $this->assertArrayHasKey('OpenapiSepPostUpdateRequest', $schemas);
    }

    public function testVisibleFieldsReflectedInOpenApiResponseSchema(): void
    {
        CrudRouteRegistrar::resource('openapi-visible-posts', OpenApiVisibleFieldsController::class)->register();

        $spec = $this->generator()->generate();
        $schema = $spec['components']['schemas']['OpenapiVisiblePostResponse'] ?? null;

        $this->assertNotNull($schema);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('title', $schema['properties']);
        $this->assertArrayHasKey('status', $schema['properties']);
        $this->assertArrayNotHasKey('body', $schema['properties']);
        $this->assertArrayNotHasKey('created_at', $schema['properties']);
    }

    public function testHiddenFieldsReflectedInOpenApiResponseSchema(): void
    {
        CrudRouteRegistrar::resource('openapi-hidden-posts', OpenApiHiddenFieldsController::class)->register();

        $spec = $this->generator()->generate();
        $schema = $spec['components']['schemas']['OpenapiHiddenPostResponse'] ?? null;

        $this->assertNotNull($schema);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayNotHasKey('body', $schema['properties']);
        // Other fields should be present
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('title', $schema['properties']);
    }
}

class OpenApiIndexOnlyController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $indexRequestClass = IndexPostRequest::class;
}

class OpenApiShowOnlyController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $showRequestClass = ShowPostRequest::class;
}

class OpenApiDestroyOnlyController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $destroyRequestClass = DestroyPostRequest::class;
}

class OpenApiSeparateRequestsController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $storeRequestClass = StorePostRequest::class;
    protected ?string $updateRequestClass = UpdatePostRequest::class;
}

class OpenApiVisibleFieldsController extends ModelCrudController
{
    protected string $model = Post::class;
    protected array $visibleFields = ['id', 'title', 'status'];
}

class OpenApiHiddenFieldsController extends ModelCrudController
{
    protected string $model = Post::class;
    protected array $hiddenFields = ['body'];
}
