<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\EdgeCases;

use Didasto\Apilot\Tests\Fixtures\Controllers\MinimalController;
use Didasto\Apilot\Tests\TestCase as BaseTestCase;
use Didasto\Apilot\Tests\PostController;

class MalformedRequestTest extends BaseTestCase
{
    public function registerTestRoutes(): void
    {
        // Controller with FormRequest for validation tests
        $this->app['router']->get('api/posts', [PostController::class, 'index']);
        $this->app['router']->get('api/posts/{id}', [PostController::class, 'show']);
        $this->app['router']->post('api/posts', [PostController::class, 'store']);
        $this->app['router']->put('api/posts/{id}', [PostController::class, 'update']);
        $this->app['router']->delete('api/posts/{id}', [PostController::class, 'destroy']);

        // Minimal controller (no FormRequest) for permissive tests
        $this->app['router']->get('api/minimal', [MinimalController::class, 'index']);
    }

    public function testNonNumericPerPageDefaultsToConfigValue(): void
    {
        $response = $this->getJson('api/minimal?per_page=abc');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 15); // config default
    }

    public function testNegativePerPageDefaultsToConfigValue(): void
    {
        $response = $this->getJson('api/minimal?per_page=-5');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 1); // min(1) kicks in
    }

    public function testZeroPerPageDefaultsToConfigValue(): void
    {
        $response = $this->getJson('api/minimal?per_page=0');

        $response->assertStatus(200);
        // 0 is non-positive, clamped to 1
        $response->assertJsonPath('meta.per_page', 1);
    }

    public function testNonNumericPageDefaultsToOne(): void
    {
        $response = $this->getJson('api/minimal?page=abc');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.current_page', 1);
    }

    public function testNegativePageDefaultsToOne(): void
    {
        $response = $this->getJson('api/minimal?page=-1');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.current_page', 1);
    }

    public function testEmptyFilterValueIsIgnored(): void
    {
        \Didasto\Apilot\Tests\Fixtures\Models\Post::factory()->count(3)->create(['status' => 'published']);

        $response = $this->getJson('api/posts?filter[status]=');

        // Empty filter value should be ignored; all 3 posts returned
        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 3);
    }

    public function testFilterAsStringInsteadOfArrayIsIgnored(): void
    {
        \Didasto\Apilot\Tests\Fixtures\Models\Post::factory()->count(2)->create();

        $response = $this->getJson('api/posts?filter=status');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }

    public function testSortAsArrayIsIgnored(): void
    {
        \Didasto\Apilot\Tests\Fixtures\Models\Post::factory()->count(2)->create();

        // ?sort[]=title becomes an array in PHP — should be ignored gracefully
        $response = $this->call('GET', 'api/posts', ['sort' => ['title']]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testExtremelyLongFilterValueDoesNotCrash(): void
    {
        $longValue = str_repeat('a', 10000);

        $response = $this->getJson("api/posts?filter[title]={$longValue}");

        // Should not crash with a PHP error; DB may truncate or return empty
        $response->assertStatus(200);
    }

    public function testShowWithNonNumericIdReturns404(): void
    {
        $response = $this->getJson('api/posts/abc');

        $response->assertStatus(404);
        $response->assertJsonPath('error.status', 404);
    }

    public function testStoreWithEmptyBodyReturnsValidationError(): void
    {
        // PostController uses PostRequest which requires 'title'
        $response = $this->postJson('api/posts', []);

        $response->assertStatus(422);
    }

    public function testStoreWithMalformedJsonReturns400Or422(): void
    {
        $response = $this->call(
            'POST',
            'api/posts',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            'this is not valid json{'
        );

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function testUpdateWithNoChangesReturns200(): void
    {
        $post = \Didasto\Apilot\Tests\Fixtures\Models\Post::factory()->create([
            'title'  => 'Same Title',
            'status' => 'draft',
        ]);

        $response = $this->putJson("api/posts/{$post->id}", [
            'title'  => 'Same Title',
            'status' => 'draft',
        ]);

        $response->assertStatus(200);
    }

    public function testDestroyAlreadyDeletedReturns404(): void
    {
        $post = \Didasto\Apilot\Tests\Fixtures\Models\Post::factory()->create();
        $id = $post->id;

        $this->deleteJson("api/posts/{$id}");
        $response = $this->deleteJson("api/posts/{$id}");

        $response->assertStatus(404);
    }
}
