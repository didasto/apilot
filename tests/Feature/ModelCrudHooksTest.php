<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature;

use Didasto\Apilot\Tests\Fixtures\Controllers\HookedPostController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\TestCase;

class ModelCrudHooksTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        HookedPostController::resetHooks();
    }

    public function registerTestRoutes(): void
    {
        parent::registerTestRoutes();

        $this->app['router']->get('api/hooked-posts', [HookedPostController::class, 'index']);
        $this->app['router']->get('api/hooked-posts/{id}', [HookedPostController::class, 'show']);
        $this->app['router']->post('api/hooked-posts', [HookedPostController::class, 'store']);
        $this->app['router']->put('api/hooked-posts/{id}', [HookedPostController::class, 'update']);
        $this->app['router']->delete('api/hooked-posts/{id}', [HookedPostController::class, 'destroy']);
    }

    public function testModifyIndexQueryHookIsCalled(): void
    {
        $this->getJson('api/hooked-posts');

        $this->assertContains('modifyIndexQuery', HookedPostController::$hooksCalled);
    }

    public function testBeforeStoreHookModifiesData(): void
    {
        $this->postJson('api/hooked-posts', [
            'title' => 'Test Post',
            'body'  => 'Some content',
        ]);

        $this->assertDatabaseHas('posts', ['title' => 'Test Post', 'status' => 'draft']);
    }

    public function testAfterStoreHookIsCalled(): void
    {
        $this->postJson('api/hooked-posts', [
            'title' => 'Test Post',
            'body'  => 'Some content',
        ]);

        $this->assertContains('afterStore', HookedPostController::$hooksCalled);
    }

    public function testBeforeUpdateHookIsCalled(): void
    {
        $post = Post::factory()->create();

        $this->putJson("api/hooked-posts/{$post->id}", [
            'title'  => 'Updated Title',
            'status' => 'draft',
        ]);

        $this->assertContains('beforeUpdate', HookedPostController::$hooksCalled);
    }

    public function testAfterUpdateHookIsCalled(): void
    {
        $post = Post::factory()->create();

        $this->putJson("api/hooked-posts/{$post->id}", [
            'title'  => 'Updated Title',
            'status' => 'draft',
        ]);

        $this->assertContains('afterUpdate', HookedPostController::$hooksCalled);
    }

    public function testBeforeDestroyHookCanPreventDeletion(): void
    {
        $post = Post::factory()->create(['status' => 'published']);

        $response = $this->deleteJson("api/hooked-posts/{$post->id}");

        $response->assertStatus(403);
        $response->assertJsonPath('error.status', 403);
        $response->assertJsonPath('error.message', 'Action not allowed.');
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function testBeforeDestroyHookAllowsDeletion(): void
    {
        $post = Post::factory()->create(['status' => 'draft']);

        $response = $this->deleteJson("api/hooked-posts/{$post->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function testAfterDestroyHookIsCalled(): void
    {
        $post = Post::factory()->create(['status' => 'draft']);

        $this->deleteJson("api/hooked-posts/{$post->id}");

        $this->assertContains('afterDestroy', HookedPostController::$hooksCalled);
    }

    public function testTransformResponseHookIsCalledForIndex(): void
    {
        $this->getJson('api/hooked-posts');

        $this->assertContains('transformResponse:index', HookedPostController::$hooksCalled);
    }

    public function testTransformResponseHookIsCalledForShow(): void
    {
        $post = Post::factory()->create();

        $this->getJson("api/hooked-posts/{$post->id}");

        $this->assertContains('transformResponse:show', HookedPostController::$hooksCalled);
    }

    public function testTransformResponseHookIsCalledForStore(): void
    {
        $this->postJson('api/hooked-posts', [
            'title' => 'Test Post',
            'body'  => 'Some content',
        ]);

        $this->assertContains('transformResponse:store', HookedPostController::$hooksCalled);
    }

    public function testTransformResponseHookIsCalledForUpdate(): void
    {
        $post = Post::factory()->create();

        $this->putJson("api/hooked-posts/{$post->id}", [
            'title'  => 'Updated Title',
            'status' => 'draft',
        ]);

        $this->assertContains('transformResponse:update', HookedPostController::$hooksCalled);
    }

    public function testTransformResponseHookIsNotCalledForDestroy(): void
    {
        $post = Post::factory()->create(['status' => 'draft']);

        $this->deleteJson("api/hooked-posts/{$post->id}");

        $this->assertNotContains('transformResponse:destroy', HookedPostController::$hooksCalled);
    }

    public function testStoreCallsHooksInCorrectOrder(): void
    {
        $this->postJson('api/hooked-posts', [
            'title' => 'Test Post',
            'body'  => 'Some content',
        ]);

        $this->assertSame(
            ['beforeStore', 'afterStore', 'transformResponse:store'],
            HookedPostController::$hooksCalled
        );
    }

    public function testUpdateCallsHooksInCorrectOrder(): void
    {
        $post = Post::factory()->create();

        $this->putJson("api/hooked-posts/{$post->id}", [
            'title'  => 'Updated Title',
            'status' => 'draft',
        ]);

        $this->assertSame(
            ['beforeUpdate', 'afterUpdate', 'transformResponse:update'],
            HookedPostController::$hooksCalled
        );
    }

    public function testDestroyCallsHooksInCorrectOrder(): void
    {
        $post = Post::factory()->create(['status' => 'draft']);

        $this->deleteJson("api/hooked-posts/{$post->id}");

        $this->assertSame(
            ['beforeDestroy', 'afterDestroy'],
            HookedPostController::$hooksCalled
        );
    }
}
