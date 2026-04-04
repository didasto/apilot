<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\PerActionRequests;

use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\AdminOnlyRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\DestroyPostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\IndexPostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\PostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\ShowPostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\StorePostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\UpdatePostRequest;
use Orchestra\Testbench\TestCase;

class FallbackChainTest extends TestCase
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
    }

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require __DIR__ . '/../../Fixtures/Migrations/create_posts_table.php';
        $migration->up();
    }

    public function testStoreRequestFallsBackToFormRequestClass(): void
    {
        $this->app['router']->post('api/store-fallback', [StoreFallbackController::class, 'store']);

        // formRequestClass (PostRequest) requires 'title'
        $response = $this->postJson('api/store-fallback', ['title' => 'Test Post']);

        $response->assertStatus(201);
    }

    public function testUpdateRequestFallsBackToFormRequestClass(): void
    {
        $this->app['router']->put('api/update-fallback/{id}', [UpdateFallbackController::class, 'update']);

        $post = Post::create(['title' => 'Original', 'body' => 'Content', 'status' => 'draft']);

        // formRequestClass (PostRequest) — update just needs title
        $response = $this->putJson("api/update-fallback/{$post->id}", ['title' => 'Updated']);

        $response->assertStatus(200);
    }

    public function testStoreRequestOverridesFormRequestClass(): void
    {
        $this->app['router']->post('api/store-override', [StoreOverrideController::class, 'store']);

        // StorePostRequest requires title+body+status
        $response = $this->postJson('api/store-override', ['status' => 'draft']);

        // Missing title and body → 422 from StorePostRequest (not PostRequest)
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'body']);
    }

    public function testAllFiveRequestClassesCanBeSetSimultaneously(): void
    {
        $this->app['router']->get('api/all-five', [AllFiveController::class, 'index']);
        $this->app['router']->get('api/all-five/{id}', [AllFiveController::class, 'show']);
        $this->app['router']->post('api/all-five', [AllFiveController::class, 'store']);
        $this->app['router']->put('api/all-five/{id}', [AllFiveController::class, 'update']);
        $this->app['router']->delete('api/all-five/{id}', [AllFiveController::class, 'destroy']);

        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $this->getJson('api/all-five')->assertStatus(200);
        $this->getJson("api/all-five/{$post->id}")->assertStatus(200);
        $this->postJson('api/all-five', ['title' => 'New', 'body' => 'Body', 'status' => 'draft'])->assertStatus(201);
        $this->putJson("api/all-five/{$post->id}", ['body' => 'Updated'])->assertStatus(200);
        $this->deleteJson("api/all-five/{$post->id}")->assertStatus(204);
    }

    public function testNoRequestClassesSetAllowsEverything(): void
    {
        $this->app['router']->get('api/no-requests', [NoRequestsController::class, 'index']);
        $this->app['router']->get('api/no-requests/{id}', [NoRequestsController::class, 'show']);
        $this->app['router']->post('api/no-requests', [NoRequestsController::class, 'store']);
        $this->app['router']->put('api/no-requests/{id}', [NoRequestsController::class, 'update']);
        $this->app['router']->delete('api/no-requests/{id}', [NoRequestsController::class, 'destroy']);

        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $this->getJson('api/no-requests')->assertStatus(200);
        $this->getJson("api/no-requests/{$post->id}")->assertStatus(200);
        $this->postJson('api/no-requests', ['title' => 'New'])->assertStatus(201);
        $this->putJson("api/no-requests/{$post->id}", ['title' => 'Updated'])->assertStatus(200);
        $this->deleteJson("api/no-requests/{$post->id}")->assertStatus(204);
    }
}

class StoreFallbackController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    // storeRequestClass not set → fallback to formRequestClass
}

class UpdateFallbackController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    // updateRequestClass not set → fallback to formRequestClass
}

class StoreOverrideController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    protected ?string $storeRequestClass = StorePostRequest::class; // overrides formRequestClass for store
}

class AllFiveController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $indexRequestClass = IndexPostRequest::class;
    protected ?string $showRequestClass = ShowPostRequest::class;
    protected ?string $storeRequestClass = StorePostRequest::class;
    protected ?string $updateRequestClass = UpdatePostRequest::class;
    protected ?string $destroyRequestClass = DestroyPostRequest::class;
}

class NoRequestsController extends ModelCrudController
{
    protected string $model = Post::class;
}
