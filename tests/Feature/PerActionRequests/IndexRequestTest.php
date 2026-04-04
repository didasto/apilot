<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\PerActionRequests;

use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\AdminOnlyRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\IndexPostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\PostRequest;
use Orchestra\Testbench\TestCase;

class IndexRequestTest extends TestCase
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

        $this->app['router']->get('api/no-index-request', [NoIndexRequestController::class, 'index']);
        $this->app['router']->get('api/allow-index', [AllowIndexController::class, 'index']);
        $this->app['router']->get('api/deny-index', [DenyIndexController::class, 'index']);
        $this->app['router']->get('api/no-fallback-index', [NoFallbackIndexController::class, 'index']);
    }

    public function testIndexWithoutRequestClassAllowsAccess(): void
    {
        $response = $this->getJson('api/no-index-request');

        $response->assertStatus(200);
    }

    public function testIndexWithRequestClassThatAuthorizes(): void
    {
        $response = $this->getJson('api/allow-index');

        $response->assertStatus(200);
    }

    public function testIndexWithRequestClassThatDenies(): void
    {
        $response = $this->getJson('api/deny-index');

        $response->assertStatus(403);
    }

    public function testIndexRequestDoesNotFallBackToFormRequestClass(): void
    {
        // NoFallbackIndexController has $formRequestClass with required rules
        // but no $indexRequestClass. The formRequestClass should NOT be used for index.
        $response = $this->getJson('api/no-fallback-index');

        // Should be 200, NOT 422 (formRequestClass rules are not applied to index)
        $response->assertStatus(200);
    }
}

class NoIndexRequestController extends ModelCrudController
{
    protected string $model = Post::class;
}

class AllowIndexController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $indexRequestClass = IndexPostRequest::class;
}

class DenyIndexController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $indexRequestClass = AdminOnlyRequest::class;
}

class NoFallbackIndexController extends ModelCrudController
{
    protected string $model = Post::class;
    // formRequestClass has required rules — but should NOT be used for index
    protected ?string $formRequestClass = PostRequest::class;
    // indexRequestClass not set → no authorization, no validation
}
