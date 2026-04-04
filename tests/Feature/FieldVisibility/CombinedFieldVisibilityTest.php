<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\FieldVisibility;

use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Controllers\CombinedVisibilityPostController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Resources\PostResource;
use Orchestra\Testbench\TestCase;

class CombinedFieldVisibilityTest extends TestCase
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

        $this->app['router']->get('api/combined-posts/{id}', [CombinedVisibilityPostController::class, 'show']);
        $this->app['router']->get('api/combined-posts', [CombinedVisibilityPostController::class, 'index']);
    }

    public function testBlacklistOverridesWhitelist(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->getJson("api/combined-posts/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('status', $data);
        // 'body' is in whitelist but also in blacklist — blacklist wins
        $this->assertArrayNotHasKey('body', $data);
    }

    public function testBlacklistAppliedAfterWhitelist(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $this->app['router']->get('api/whitelist-then-blacklist/{id}', [WhitelistThenBlacklistController::class, 'show']);

        $response = $this->getJson("api/whitelist-then-blacklist/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        // 'body' is in whitelist but blacklisted → removed
        $this->assertArrayNotHasKey('body', $data);
    }

    public function testResourceClassIgnoresVisibilitySettings(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $this->app['router']->get('api/resource-with-visible/{id}', [ResourceWithVisibleController::class, 'show']);

        $response = $this->getJson("api/resource-with-visible/{$post->id}");

        $response->assertStatus(200);
        // PostResource exposes these fields — visibleFields is ignored
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
    }

    public function testResourceClassIgnoresHiddenFields(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $this->app['router']->get('api/resource-with-hidden/{id}', [ResourceWithHiddenController::class, 'show']);

        $response = $this->getJson("api/resource-with-hidden/{$post->id}");

        $response->assertStatus(200);
        // PostResource exposes 'body' — hiddenFields is ignored when resourceClass is set
        $data = $response->json('data');
        $this->assertArrayHasKey('body', $data);
    }

    public function testNoVisibilityNoBlacklistShowsAllFields(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $this->app['router']->get('api/no-visibility/{id}', [NoVisibilityController::class, 'show']);

        $response = $this->getJson("api/no-visibility/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayHasKey('status', $data);
    }
}

class WhitelistThenBlacklistController extends ModelCrudController
{
    protected string $model = Post::class;
    protected array $visibleFields = ['id', 'title', 'body'];
    protected array $hiddenFields = ['body'];
}

class ResourceWithVisibleController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $resourceClass = PostResource::class;
    protected array $visibleFields = ['id', 'title']; // ignored because resourceClass is set
}

class ResourceWithHiddenController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $resourceClass = PostResource::class;
    protected array $hiddenFields = ['body']; // ignored because resourceClass is set
}

class NoVisibilityController extends ModelCrudController
{
    protected string $model = Post::class;
}
