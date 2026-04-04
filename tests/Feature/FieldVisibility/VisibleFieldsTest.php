<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\FieldVisibility;

use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Controllers\WhitelistPostController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Orchestra\Testbench\TestCase;

class VisibleFieldsTest extends TestCase
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

        $this->app['router']->get('api/whitelist-posts', [WhitelistPostController::class, 'index']);
        $this->app['router']->get('api/whitelist-posts/{id}', [WhitelistPostController::class, 'show']);
        $this->app['router']->post('api/whitelist-posts', [WhitelistPostController::class, 'store']);
        $this->app['router']->put('api/whitelist-posts/{id}', [WhitelistPostController::class, 'update']);
    }

    public function testWhitelistShowsOnlySpecifiedFields(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->getJson("api/whitelist-posts/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayNotHasKey('body', $data);
        $this->assertArrayNotHasKey('created_at', $data);
    }

    public function testWhitelistWorksOnIndex(): void
    {
        Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->getJson('api/whitelist-posts');

        $response->assertStatus(200);
        $items = $response->json('data');
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayNotHasKey('body', $item);
            $this->assertArrayNotHasKey('created_at', $item);
        }
    }

    public function testWhitelistWorksOnStore(): void
    {
        $response = $this->postJson('api/whitelist-posts', [
            'title'  => 'New Post',
            'body'   => 'Content',
            'status' => 'draft',
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayNotHasKey('body', $data);
    }

    public function testWhitelistWorksOnUpdate(): void
    {
        $post = Post::create(['title' => 'Original', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->putJson("api/whitelist-posts/{$post->id}", [
            'title' => 'Updated',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayNotHasKey('body', $data);
    }

    public function testEmptyWhitelistShowsAllFields(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $this->app['router']->get('api/empty-whitelist/{id}', [EmptyWhitelistController::class, 'show']);

        $response = $this->getJson("api/empty-whitelist/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('created_at', $data);
    }

    public function testWhitelistWithNonExistentFieldIsIgnored(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $this->app['router']->get('api/nonexistent-field/{id}', [NonExistentFieldController::class, 'show']);

        $response = $this->getJson("api/nonexistent-field/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayNotHasKey('nonexistent', $data);
    }
}

class EmptyWhitelistController extends ModelCrudController
{
    protected string $model = Post::class;
    protected array $visibleFields = [];
}

class NonExistentFieldController extends ModelCrudController
{
    protected string $model = Post::class;
    protected array $visibleFields = ['id', 'title', 'nonexistent'];
}
