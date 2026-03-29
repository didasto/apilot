<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\EdgeCases;

use Didasto\Apilot\Tests\Fixtures\Controllers\MinimalController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\TestCase;

class EmptyControllerTest extends TestCase
{
    public function registerTestRoutes(): void
    {
        $this->app['router']->get('api/minimal', [MinimalController::class, 'index']);
        $this->app['router']->get('api/minimal/{id}', [MinimalController::class, 'show']);
        $this->app['router']->post('api/minimal', [MinimalController::class, 'store']);
        $this->app['router']->put('api/minimal/{id}', [MinimalController::class, 'update']);
        $this->app['router']->delete('api/minimal/{id}', [MinimalController::class, 'destroy']);
    }

    public function testMinimalControllerIndexReturnsResults(): void
    {
        Post::factory()->count(3)->create();

        $response = $this->getJson('api/minimal');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function testMinimalControllerStoreAcceptsAnyData(): void
    {
        $response = $this->postJson('api/minimal', [
            'title' => 'Test Post',
            'body'  => 'Some content',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', ['title' => 'Test Post']);
    }

    public function testMinimalControllerShowReturnsDefaultResource(): void
    {
        $post = Post::factory()->create(['title' => 'Visible Post']);

        $response = $this->getJson("api/minimal/{$post->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Visible Post');
        $response->assertJsonPath('data.id', $post->id);
    }

    public function testMinimalControllerUpdateWithoutValidation(): void
    {
        $post = Post::factory()->create(['title' => 'Old Title']);

        $response = $this->putJson("api/minimal/{$post->id}", [
            'title' => 'New Title',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'New Title']);
    }

    public function testMinimalControllerDestroyWorks(): void
    {
        $post = Post::factory()->create();

        $response = $this->deleteJson("api/minimal/{$post->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function testMinimalControllerIgnoresFilterParams(): void
    {
        Post::factory()->count(5)->create();
        Post::factory()->create(['title' => 'Only This']);

        $response = $this->getJson('api/minimal?filter[title]=Only+This');

        // No allowed filters defined, so all 6 posts are returned
        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 6);
    }

    public function testMinimalControllerIgnoresSortParams(): void
    {
        Post::factory()->create(['title' => 'B Post']);
        Post::factory()->create(['title' => 'A Post']);

        $response = $this->getJson('api/minimal?sort=title');

        // No allowed sorts defined, so sort is ignored — both are returned
        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }
}
