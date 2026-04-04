<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\PerActionRequests;

use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\AdminOnlyRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\PostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\ShowPostRequest;
use Orchestra\Testbench\TestCase;

class ShowRequestTest extends TestCase
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

        $this->app['router']->get('api/no-show-request/{id}', [NoShowRequestController::class, 'show']);
        $this->app['router']->get('api/allow-show/{id}', [AllowShowController::class, 'show']);
        $this->app['router']->get('api/deny-show/{id}', [DenyShowController::class, 'show']);
        $this->app['router']->get('api/no-fallback-show/{id}', [NoFallbackShowController::class, 'show']);
    }

    public function testShowWithoutRequestClassAllowsAccess(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->getJson("api/no-show-request/{$post->id}");

        $response->assertStatus(200);
    }

    public function testShowWithRequestClassThatAuthorizes(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->getJson("api/allow-show/{$post->id}");

        $response->assertStatus(200);
    }

    public function testShowWithRequestClassThatDenies(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->getJson("api/deny-show/{$post->id}");

        $response->assertStatus(403);
    }

    public function testShowRequestDoesNotFallBackToFormRequestClass(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        // NoFallbackShowController has $formRequestClass with required rules
        // but no $showRequestClass. formRequestClass should NOT be used for show.
        $response = $this->getJson("api/no-fallback-show/{$post->id}");

        // Should be 200, NOT 422
        $response->assertStatus(200);
    }
}

class NoShowRequestController extends ModelCrudController
{
    protected string $model = Post::class;
}

class AllowShowController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $showRequestClass = ShowPostRequest::class;
}

class DenyShowController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $showRequestClass = AdminOnlyRequest::class;
}

class NoFallbackShowController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    // showRequestClass not set → no auth for show
}
