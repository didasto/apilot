<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\PerActionRequests;

use Illuminate\Http\Request;
use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Controllers\Concerns\HasCrudHooks;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\AdminOnlyRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\DestroyPostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\PostRequest;
use Orchestra\Testbench\TestCase;

class DestroyRequestTest extends TestCase
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

        $this->app['router']->delete('api/no-destroy-request/{id}', [NoDestroyRequestController::class, 'destroy']);
        $this->app['router']->delete('api/allow-destroy/{id}', [AllowDestroyController::class, 'destroy']);
        $this->app['router']->delete('api/deny-destroy/{id}', [DenyDestroyController::class, 'destroy']);
        $this->app['router']->delete('api/no-fallback-destroy/{id}', [NoFallbackDestroyController::class, 'destroy']);
        $this->app['router']->delete('api/hook-deny-destroy/{id}', [HookDenyDestroyController::class, 'destroy']);
    }

    public function testDestroyWithoutRequestClassAllowsAccess(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->deleteJson("api/no-destroy-request/{$post->id}");

        $response->assertStatus(204);
    }

    public function testDestroyWithRequestClassThatAuthorizes(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->deleteJson("api/allow-destroy/{$post->id}");

        $response->assertStatus(204);
    }

    public function testDestroyWithRequestClassThatDenies(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->deleteJson("api/deny-destroy/{$post->id}");

        $response->assertStatus(403);
    }

    public function testDestroyRequestDoesNotFallBackToFormRequestClass(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        // formRequestClass has required rules but destroyRequestClass is not set
        $response = $this->deleteJson("api/no-fallback-destroy/{$post->id}");

        // Should be 204, NOT 422
        $response->assertStatus(204);
    }

    public function testDestroyRequestAndBeforeDestroyHookBothActive(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        // destroyRequestClass allows (authorize → true)
        // beforeDestroy hook returns false → ActionNotAllowedException → 403
        $response = $this->deleteJson("api/hook-deny-destroy/{$post->id}");

        $response->assertStatus(403);
        // Post should still exist (not deleted because hook blocked it)
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }
}

class NoDestroyRequestController extends ModelCrudController
{
    protected string $model = Post::class;
}

class AllowDestroyController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $destroyRequestClass = DestroyPostRequest::class;
}

class DenyDestroyController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $destroyRequestClass = AdminOnlyRequest::class;
}

class NoFallbackDestroyController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    // destroyRequestClass not set → no auth for destroy
}

class HookDenyDestroyController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $destroyRequestClass = DestroyPostRequest::class; // authorize → true

    protected function beforeDestroy(mixed $item, Request $request): bool
    {
        return false; // hook denies deletion
    }
}
