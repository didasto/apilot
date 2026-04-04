<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\FieldVisibility;

use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Controllers\BlacklistPostController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Orchestra\Testbench\TestCase;

class HiddenFieldsTest extends TestCase
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

        $this->app['router']->get('api/blacklist-posts', [BlacklistPostController::class, 'index']);
        $this->app['router']->get('api/blacklist-posts/{id}', [BlacklistPostController::class, 'show']);
        $this->app['router']->post('api/blacklist-posts', [BlacklistPostController::class, 'store']);
        $this->app['router']->put('api/blacklist-posts/{id}', [BlacklistPostController::class, 'update']);
    }

    public function testBlacklistHidesSpecifiedFields(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->getJson("api/blacklist-posts/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayNotHasKey('body', $data);
        $this->assertArrayNotHasKey('updated_at', $data);
        // Other fields should be present
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('status', $data);
    }

    public function testBlacklistWorksOnIndex(): void
    {
        Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->getJson('api/blacklist-posts');

        $response->assertStatus(200);
        $items = $response->json('data');
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertArrayNotHasKey('body', $item);
            $this->assertArrayNotHasKey('updated_at', $item);
            $this->assertArrayHasKey('title', $item);
        }
    }

    public function testBlacklistWorksOnStore(): void
    {
        $response = $this->postJson('api/blacklist-posts', [
            'title'  => 'New Post',
            'body'   => 'Content',
            'status' => 'draft',
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertArrayNotHasKey('body', $data);
        $this->assertArrayNotHasKey('updated_at', $data);
        $this->assertArrayHasKey('title', $data);
    }

    public function testBlacklistWorksOnUpdate(): void
    {
        $post = Post::create(['title' => 'Original', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->putJson("api/blacklist-posts/{$post->id}", [
            'title' => 'Updated',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayNotHasKey('body', $data);
        $this->assertArrayNotHasKey('updated_at', $data);
        $this->assertArrayHasKey('title', $data);
    }

    public function testEmptyBlacklistShowsAllFields(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $this->app['router']->get('api/empty-blacklist/{id}', [EmptyBlacklistController::class, 'show']);

        $response = $this->getJson("api/empty-blacklist/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('updated_at', $data);
    }

    public function testBlacklistWithNonExistentFieldIsIgnored(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $this->app['router']->get('api/nonexistent-hidden/{id}', [NonExistentHiddenController::class, 'show']);

        $response = $this->getJson("api/nonexistent-hidden/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        // All normal fields present
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
    }
}

class EmptyBlacklistController extends ModelCrudController
{
    protected string $model = Post::class;
    protected array $hiddenFields = [];
}

class NonExistentHiddenController extends ModelCrudController
{
    protected string $model = Post::class;
    protected array $hiddenFields = ['nonexistent'];
}
