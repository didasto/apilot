<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature;

use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\TestCase;

class ModelCrudControllerTest extends TestCase
{
    public function testIndexReturnsEmptyPaginatedList(): void
    {
        $response = $this->getJson('api/posts');

        $response->assertStatus(200);
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.total', 0);
    }

    public function testIndexReturnsPaginatedResults(): void
    {
        Post::factory()->count(20)->create();

        $response = $this->getJson('api/posts');

        $response->assertStatus(200);
        $response->assertJsonCount(15, 'data');
        $response->assertJsonPath('meta.total', 20);
        $response->assertJsonPath('meta.per_page', 15);
    }

    public function testIndexRespectsPerPageParameter(): void
    {
        Post::factory()->count(20)->create();

        $response = $this->getJson('api/posts?per_page=5');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.per_page', 5);
    }

    public function testIndexRespectsMaxPerPage(): void
    {
        Post::factory()->count(5)->create();

        $response = $this->getJson('api/posts?per_page=999');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 100);
    }

    public function testIndexWithSortingAscending(): void
    {
        Post::factory()->create(['title' => 'Zebra']);
        Post::factory()->create(['title' => 'Apple']);
        Post::factory()->create(['title' => 'Mango']);

        $response = $this->getJson('api/posts?sort=title');

        $response->assertStatus(200);

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertSame(['Apple', 'Mango', 'Zebra'], $titles);
    }

    public function testIndexWithSortingDescending(): void
    {
        Post::factory()->create(['title' => 'Zebra']);
        Post::factory()->create(['title' => 'Apple']);
        Post::factory()->create(['title' => 'Mango']);

        $response = $this->getJson('api/posts?sort=-title');

        $response->assertStatus(200);

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertSame(['Zebra', 'Mango', 'Apple'], $titles);
    }

    public function testIndexWithMultipleSortFields(): void
    {
        Post::factory()->create(['title' => 'Beta', 'status' => 'published']);
        Post::factory()->create(['title' => 'Alpha', 'status' => 'published']);
        Post::factory()->create(['title' => 'Gamma', 'status' => 'draft']);

        $response = $this->getJson('api/posts?sort=status,-title');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertSame('draft', $data[0]['status']);
        $this->assertSame('Gamma', $data[0]['title']);
        $this->assertSame('published', $data[1]['status']);
        $this->assertSame('Beta', $data[1]['title']);
        $this->assertSame('Alpha', $data[2]['title']);
    }

    public function testIndexIgnoresDisallowedSortFields(): void
    {
        Post::factory()->create(['title' => 'Post A', 'body' => 'Z body']);
        Post::factory()->create(['title' => 'Post B', 'body' => 'A body']);

        $response = $this->getJson('api/posts?sort=body');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function testIndexWithExactFilter(): void
    {
        Post::factory()->create(['status' => 'published']);
        Post::factory()->create(['status' => 'draft']);
        Post::factory()->create(['status' => 'published']);

        $response = $this->getJson('api/posts?filter[status]=published');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 2);
    }

    public function testIndexWithPartialFilter(): void
    {
        Post::factory()->create(['title' => 'Hello World']);
        Post::factory()->create(['title' => 'Foo Bar']);
        Post::factory()->create(['title' => 'Hello Laravel']);

        $response = $this->getJson('api/posts?filter[title]=Hello');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function testIndexWithScopeFilter(): void
    {
        Post::factory()->create(['status' => 'draft']);
        Post::factory()->create(['status' => 'published']);
        Post::factory()->create(['status' => 'draft']);

        $response = $this->getJson('api/posts-scope?filter[status]=draft');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function testIndexIgnoresDisallowedFilters(): void
    {
        Post::factory()->create(['body' => 'test content']);
        Post::factory()->create(['body' => 'other content']);

        $response = $this->getJson('api/posts?filter[body]=test');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function testShowReturnsResource(): void
    {
        $post = Post::factory()->create(['title' => 'My Post']);

        $response = $this->getJson("api/posts/{$post->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $post->id);
        $response->assertJsonPath('data.title', 'My Post');
    }

    public function testShowReturns404ForMissingResource(): void
    {
        $response = $this->getJson('api/posts/9999');

        $response->assertStatus(404);
        $response->assertJsonPath('error.status', 404);
        $response->assertJsonPath('error.message', 'Resource not found.');
    }

    public function testStoreCreatesResource(): void
    {
        $response = $this->postJson('api/posts', [
            'title'  => 'New Post',
            'body'   => 'Some content',
            'status' => 'draft',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'New Post');
        $this->assertDatabaseHas('posts', ['title' => 'New Post']);
    }

    public function testStoreValidatesInput(): void
    {
        $response = $this->postJson('api/posts', [
            'body' => 'No title provided',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function testUpdateModifiesResource(): void
    {
        $post = Post::factory()->create(['title' => 'Old Title']);

        $response = $this->putJson("api/posts/{$post->id}", [
            'title'  => 'New Title',
            'status' => 'published',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'New Title');
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'New Title']);
    }

    public function testUpdateReturns404ForMissingResource(): void
    {
        $response = $this->putJson('api/posts/9999', [
            'title' => 'Some Title',
        ]);

        $response->assertStatus(404);
    }

    public function testUpdateValidatesInput(): void
    {
        $post = Post::factory()->create();

        $response = $this->putJson("api/posts/{$post->id}", [
            'title' => str_repeat('x', 256),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function testDestroyDeletesResource(): void
    {
        $post = Post::factory()->create();

        $response = $this->deleteJson("api/posts/{$post->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function testDestroyReturns404ForMissingResource(): void
    {
        $response = $this->deleteJson('api/posts/9999');

        $response->assertStatus(404);
    }

    public function testControllerWithoutFormRequestAcceptsAnyData(): void
    {
        $response = $this->postJson('api/posts-no-request', [
            'title'        => 'No Validation Post',
            'random_field' => 'will be ignored by fillable',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', ['title' => 'No Validation Post']);
    }

    public function testControllerWithoutResourceClassUsesDefaultResource(): void
    {
        $post = Post::factory()->create(['title' => 'Default Resource Post']);

        $response = $this->getJson("api/posts-no-resource/{$post->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Default Resource Post');
    }
}
