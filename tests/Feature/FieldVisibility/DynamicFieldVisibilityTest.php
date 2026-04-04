<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\FieldVisibility;

use Illuminate\Http\Request;
use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Tests\Fixtures\Controllers\DynamicVisibilityPostController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Orchestra\Testbench\TestCase;

class DynamicFieldVisibilityTest extends TestCase
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

        $this->app['router']->get('api/dynamic-posts', [DynamicVisibilityPostController::class, 'index']);
        $this->app['router']->get('api/dynamic-posts/{id}', [DynamicVisibilityPostController::class, 'show']);
    }

    public function testDynamicVisibleFieldsForAdmin(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->getJson("api/dynamic-posts/{$post->id}", ['X-Role' => 'admin']);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
    }

    public function testDynamicVisibleFieldsForNonAdmin(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->getJson("api/dynamic-posts/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayNotHasKey('body', $data);
        $this->assertArrayNotHasKey('created_at', $data);
    }

    public function testDynamicVisibleFieldsOnIndexAsAdmin(): void
    {
        Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->getJson('api/dynamic-posts', ['X-Role' => 'admin']);

        $response->assertStatus(200);
        $items = $response->json('data');
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertArrayHasKey('body', $item);
            $this->assertArrayHasKey('created_at', $item);
        }
    }

    public function testDynamicVisibleFieldsOnIndexAsNonAdmin(): void
    {
        Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $response = $this->getJson('api/dynamic-posts');

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

    public function testMethodOverridesProperty(): void
    {
        $post = Post::create(['title' => 'Test', 'body' => 'Content', 'status' => 'draft']);

        $this->app['router']->get('api/method-override/{id}', [MethodOverrideController::class, 'show']);

        $response = $this->getJson("api/method-override/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        // Method returns ['id', 'title'] — overrides the property ['id']
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayNotHasKey('body', $data);
        $this->assertArrayNotHasKey('status', $data);
    }
}

class MethodOverrideController extends ModelCrudController
{
    protected string $model = Post::class;
    protected array $visibleFields = ['id']; // property says only id

    protected function visibleFields(Request $request): array
    {
        return ['id', 'title']; // method overrides — returns id and title
    }
}
