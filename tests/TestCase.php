<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests;

use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\PostRequest;
use Didasto\Apilot\Tests\Fixtures\Resources\PostResource;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    public function getPackageProviders($app): array
    {
        return [
            ApilotServiceProvider::class,
        ];
    }

    public function  setUp(): void
    {
        parent::setUp();

        $this->runMigrations();
        $this->registerTestRoutes();
    }

    public function  runMigrations(): void
    {
        $migration = require __DIR__ . '/Fixtures/Migrations/create_posts_table.php';
        $migration->up();
    }

    public function  registerTestRoutes(): void
    {
        $this->app['router']->get('api/posts', [PostController::class, 'index']);
        $this->app['router']->get('api/posts/{id}', [PostController::class, 'show']);
        $this->app['router']->post('api/posts', [PostController::class, 'store']);
        $this->app['router']->put('api/posts/{id}', [PostController::class, 'update']);
        $this->app['router']->delete('api/posts/{id}', [PostController::class, 'destroy']);

        $this->app['router']->get('api/posts-no-request', [PostControllerWithoutFormRequest::class, 'index']);
        $this->app['router']->post('api/posts-no-request', [PostControllerWithoutFormRequest::class, 'store']);

        $this->app['router']->get('api/posts-no-resource', [PostControllerWithoutResource::class, 'index']);
        $this->app['router']->get('api/posts-no-resource/{id}', [PostControllerWithoutResource::class, 'show']);

        $this->app['router']->get('api/posts-scope', [PostControllerWithScope::class, 'index']);
    }

    public function  defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
}

class PostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    protected ?string $resourceClass = PostResource::class;

    protected array $allowedFilters = [
        'status' => AllowedFilter::EXACT,
        'title'  => AllowedFilter::PARTIAL,
    ];

    protected array $allowedSorts = ['title', 'created_at', 'updated_at'];
}

class PostControllerWithoutFormRequest extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = null;
    protected ?string $resourceClass = PostResource::class;

    protected array $allowedSorts = ['title'];
}

class PostControllerWithoutResource extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = null;
    protected ?string $resourceClass = null;

    protected array $allowedSorts = ['title'];
}

class PostControllerWithScope extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = null;
    protected ?string $resourceClass = PostResource::class;

    protected array $allowedFilters = [
        'status' => AllowedFilter::SCOPE,
    ];
}
