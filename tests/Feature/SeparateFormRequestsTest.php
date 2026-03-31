<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature;

use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\OpenApi\OpenApiGenerator;
use Didasto\Apilot\Routing\CrudRouteRegistrar;
use Didasto\Apilot\Routing\RouteRegistry;
use Didasto\Apilot\Tests\Fixtures\Controllers\SeparateRequestPostController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\PostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\StorePostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\UpdatePostRequest;
use Didasto\Apilot\Tests\Fixtures\Resources\PostResource;
use Orchestra\Testbench\TestCase;

class SeparateFormRequestsTest extends TestCase
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

        $migration = require __DIR__ . '/../Fixtures/Migrations/create_posts_table.php';
        $migration->up();

        $this->app->make(RouteRegistry::class)->clear();

        $this->app['router']->post('api/sep-posts', [SeparateRequestPostController::class, 'store']);
        $this->app['router']->put('api/sep-posts/{id}', [SeparateRequestPostController::class, 'update']);
        $this->app['router']->get('api/sep-posts', [SeparateRequestPostController::class, 'index']);
        $this->app['router']->get('api/sep-posts/{id}', [SeparateRequestPostController::class, 'show']);
    }

    protected function generator(): OpenApiGenerator
    {
        return $this->app->make(OpenApiGenerator::class);
    }

    // =========================================================================
    // Tests
    // =========================================================================

    public function testStoreUsesStoreRequestClass(): void
    {
        $response = $this->postJson('api/sep-posts', [
            'title'  => 'Test',
            'body'   => 'Content',
            'status' => 'draft',
        ]);

        $response->assertStatus(201);
    }

    public function testStoreRejectsFieldsNotInStoreRequest(): void
    {
        // StorePostRequest requires title, body, status → missing → 422
        $response = $this->postJson('api/sep-posts', [
            'status' => 'draft',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'body']);
    }

    public function testUpdateUsesUpdateRequestClass(): void
    {
        $post = Post::create(['title' => 'Original', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->putJson("api/sep-posts/{$post->id}", [
            'body' => 'Updated content',
        ]);

        $response->assertStatus(200);
    }

    public function testUpdateAcceptsPartialData(): void
    {
        $post = Post::create(['title' => 'Original', 'body' => 'Content', 'status' => 'draft']);

        // UpdatePostRequest hat 'sometimes' → nur body übergeben → OK
        $response = $this->putJson("api/sep-posts/{$post->id}", [
            'body' => 'New content',
        ]);

        $response->assertStatus(200);
    }

    public function testFallbackToFormRequestClassWhenSpecificNotSet(): void
    {
        $this->app['router']->post('api/fallback-posts', [FallbackController::class, 'store']);
        $this->app['router']->put('api/fallback-posts/{id}', [FallbackController::class, 'update']);

        // Store: braucht title (aus PostRequest)
        $response = $this->postJson('api/fallback-posts', [
            'title' => 'Test post',
        ]);
        $response->assertStatus(201);
    }

    public function testStoreRequestFallsBackToFormRequestClass(): void
    {
        $this->app['router']->post('api/store-fallback-posts', [StoreOnlyController::class, 'store']);

        // Store uses formRequestClass (PostRequest) since storeRequestClass not set
        $response = $this->postJson('api/store-fallback-posts', [
            'title' => 'Test post',
        ]);
        $response->assertStatus(201);
    }

    public function testUpdateRequestFallsBackToFormRequestClass(): void
    {
        $this->app['router']->put('api/update-fallback-posts/{id}', [UpdateOnlyController::class, 'update']);

        $post = Post::create(['title' => 'Original', 'body' => 'Content', 'status' => 'draft']);

        // Update uses formRequestClass (PostRequest) since updateRequestClass not set
        $response = $this->putJson("api/update-fallback-posts/{$post->id}", [
            'title' => 'Updated',
        ]);
        $response->assertStatus(200);
    }

    public function testAllThreeRequestClassesCanBeSetSimultaneously(): void
    {
        $this->app['router']->post('api/all-three-posts', [AllThreeController::class, 'store']);
        $this->app['router']->put('api/all-three-posts/{id}', [AllThreeController::class, 'update']);

        // Store should use StorePostRequest (requires title, body, status)
        $response = $this->postJson('api/all-three-posts', ['status' => 'draft']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'body']);

        // Create a post for update test
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        // Update should use UpdatePostRequest (only body, status with sometimes)
        $response = $this->putJson("api/all-three-posts/{$post->id}", ['body' => 'Updated']);
        $response->assertStatus(200);
    }

    public function testOpenApiSpecHasSeparateStoreAndUpdateSchemas(): void
    {
        CrudRouteRegistrar::resource('sep-posts', SeparateRequestPostController::class)
            ->register();

        $spec    = $this->generator()->generate();
        $schemas = $spec['components']['schemas'];

        $this->assertArrayHasKey('SepPostStoreRequest', $schemas);
        $this->assertArrayHasKey('SepPostUpdateRequest', $schemas);
        $this->assertArrayNotHasKey('SepPostRequest', $schemas);
    }

    public function testOpenApiSpecHasSingleSchemaWhenBothAreSame(): void
    {
        CrudRouteRegistrar::resource('same-posts', SameRequestController::class)
            ->register();

        $spec    = $this->generator()->generate();
        $schemas = $spec['components']['schemas'];

        $this->assertArrayHasKey('SamePostRequest', $schemas);
        $this->assertArrayNotHasKey('SamePostStoreRequest', $schemas);
        $this->assertArrayNotHasKey('SamePostUpdateRequest', $schemas);
    }

    public function testOpenApiSpecStoreEndpointReferencesStoreSchema(): void
    {
        CrudRouteRegistrar::resource('sep-posts', SeparateRequestPostController::class)
            ->register();

        $spec   = $this->generator()->generate();
        $storeOp = $spec['paths']['/api/sep-posts']['post'];

        $schema = $storeOp['requestBody']['content']['application/json']['schema'];
        $this->assertEquals('#/components/schemas/SepPostStoreRequest', $schema['$ref']);
    }

    public function testOpenApiSpecUpdateEndpointReferencesUpdateSchema(): void
    {
        CrudRouteRegistrar::resource('sep-posts', SeparateRequestPostController::class)
            ->register();

        $spec     = $this->generator()->generate();
        $updateOp = $spec['paths']['/api/sep-posts/{id}']['put'];

        $schema = $updateOp['requestBody']['content']['application/json']['schema'];
        $this->assertEquals('#/components/schemas/SepPostUpdateRequest', $schema['$ref']);
    }
}

// ---------------------------------------------------------------------------
// Inline-Fixtures
// ---------------------------------------------------------------------------

class FallbackController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    // Kein storeRequestClass / updateRequestClass → Fallback auf formRequestClass
}

class StoreOnlyController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    protected ?string $updateRequestClass = UpdatePostRequest::class;
    // storeRequestClass nicht gesetzt → Fallback auf formRequestClass
}

class UpdateOnlyController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    protected ?string $storeRequestClass = StorePostRequest::class;
    // updateRequestClass nicht gesetzt → Fallback auf formRequestClass
}

class AllThreeController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    protected ?string $storeRequestClass = StorePostRequest::class;
    protected ?string $updateRequestClass = UpdatePostRequest::class;
}

class SameRequestController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $storeRequestClass = PostRequest::class;
    protected ?string $updateRequestClass = PostRequest::class;
}
