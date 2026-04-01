<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature;

use Didasto\Apilot\OpenApi\OpenApiGenerator;
use Didasto\Apilot\Routing\CrudRouteRegistrar;
use Didasto\Apilot\Routing\RouteRegistry;
use Didasto\Apilot\Tests\Fixtures\Controllers\HookedPostController;
use Didasto\Apilot\Tests\Fixtures\Controllers\PostController as PostControllerFixture;
use Didasto\Apilot\Tests\Fixtures\Controllers\TagController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Services\TagService;
use Didasto\Apilot\Tests\TestCase;

class ResponseWrapperTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        HookedPostController::resetHooks();
        TagService::reset();
    }

    public function registerTestRoutes(): void
    {
        parent::registerTestRoutes();

        $this->app['router']->get('api/hooked-posts', [HookedPostController::class, 'index']);
        $this->app['router']->get('api/hooked-posts/{id}', [HookedPostController::class, 'show']);
        $this->app['router']->post('api/hooked-posts', [HookedPostController::class, 'store']);
        $this->app['router']->put('api/hooked-posts/{id}', [HookedPostController::class, 'update']);
        $this->app['router']->delete('api/hooked-posts/{id}', [HookedPostController::class, 'destroy']);

        $this->app['router']->get('api/tags', [TagController::class, 'index']);
        $this->app['router']->get('api/tags/{id}', [TagController::class, 'show']);
        $this->app['router']->post('api/tags', [TagController::class, 'store']);
        $this->app['router']->put('api/tags/{id}', [TagController::class, 'update']);
        $this->app['router']->delete('api/tags/{id}', [TagController::class, 'destroy']);
    }

    // =========================================================================
    // Mode 1: null — Laravel Default
    // =========================================================================

    public function testNullWrapperShowReturnsLaravelDefault(): void
    {
        config()->set('apilot.response_wrapper', null);
        $post = Post::factory()->create(['title' => 'Post 1']);

        $response = $this->getJson("api/posts/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals($post->id, $data['data']['id']);
    }

    public function testNullWrapperIndexReturnsLaravelDefault(): void
    {
        config()->set('apilot.response_wrapper', null);
        Post::factory()->count(5)->create();

        $response = $this->getJson('api/posts');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('links', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertIsArray($data['data']);
        $this->assertCount(5, $data['data']);
    }

    public function testNullWrapperStoreReturnsLaravelDefault(): void
    {
        config()->set('apilot.response_wrapper', null);

        $response = $this->postJson('api/posts', [
            'title' => 'New Post',
            'body'  => 'Content',
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('New Post', $data['data']['title']);
    }

    public function testNullWrapperUpdateReturnsLaravelDefault(): void
    {
        config()->set('apilot.response_wrapper', null);
        $post = Post::factory()->create();

        $response = $this->putJson("api/posts/{$post->id}", [
            'title'  => 'Updated',
            'status' => 'draft',
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('Updated', $data['data']['title']);
    }

    // =========================================================================
    // Mode 2: [] — No Wrapper
    // =========================================================================

    public function testEmptyArrayWrapperShowReturnsUnwrapped(): void
    {
        config()->set('apilot.response_wrapper', []);
        $post = Post::factory()->create(['title' => 'Post 1']);

        $response = $this->getJson("api/posts/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertEquals($post->id, $data['id']);
    }

    public function testEmptyArrayWrapperStoreReturnsUnwrapped(): void
    {
        config()->set('apilot.response_wrapper', []);

        $response = $this->postJson('api/posts', [
            'title' => 'New Post',
            'body'  => 'Content',
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertEquals('New Post', $data['title']);
    }

    public function testEmptyArrayWrapperUpdateReturnsUnwrapped(): void
    {
        config()->set('apilot.response_wrapper', []);
        $post = Post::factory()->create();

        $response = $this->putJson("api/posts/{$post->id}", [
            'title'  => 'Updated Post',
            'status' => 'draft',
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertEquals('Updated Post', $data['title']);
    }

    public function testEmptyArrayWrapperIndexReturnsPlainArray(): void
    {
        config()->set('apilot.response_wrapper', []);
        Post::factory()->count(5)->create();

        $response = $this->getJson('api/posts');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        // Plain JSON array — no wrapper keys, no meta, no links
        $this->assertArrayNotHasKey('items', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertArrayNotHasKey('meta', $data);
        $this->assertArrayNotHasKey('links', $data);
    }

    public function testEmptyArrayWrapperIndexItemsContainsPosts(): void
    {
        config()->set('apilot.response_wrapper', []);
        Post::factory()->count(3)->create();

        $response = $this->getJson('api/posts');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(3, $data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('title', $data[0]);
    }

    public function testEmptyArrayWrapperIndexReturnsAllItems(): void
    {
        config()->set('apilot.response_wrapper', []);
        Post::factory()->count(7)->create();

        $response = $this->getJson('api/posts');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(7, $data);
    }

    public function testEmptyArrayWrapperIndexStillRespectsPagination(): void
    {
        config()->set('apilot.response_wrapper', []);
        Post::factory()->count(20)->create();

        $response = $this->getJson('api/posts?per_page=5');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(5, $data);
    }

    // =========================================================================
    // Mode 3: 'string' — Named Wrapper
    // =========================================================================

    public function testStringWrapperDataShowWrapsInData(): void
    {
        config()->set('apilot.response_wrapper', 'data');
        $post = Post::factory()->create();

        $response = $this->getJson("api/posts/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals($post->id, $data['data']['id']);
    }

    public function testStringWrapperDataIndexWrapsInData(): void
    {
        config()->set('apilot.response_wrapper', 'data');
        Post::factory()->count(3)->create();

        $response = $this->getJson('api/posts');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('links', $data);
        $this->assertIsArray($data['data']);
    }

    public function testStringWrapperResultShowWrapsInResult(): void
    {
        config()->set('apilot.response_wrapper', 'result');
        $post = Post::factory()->create();

        $response = $this->getJson("api/posts/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('result', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertEquals($post->id, $data['result']['id']);
    }

    public function testStringWrapperResultIndexWrapsInResult(): void
    {
        config()->set('apilot.response_wrapper', 'result');
        Post::factory()->count(3)->create();

        $response = $this->getJson('api/posts');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('result', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('links', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertIsArray($data['result']);
    }

    public function testStringWrapperResultStoreWrapsInResult(): void
    {
        config()->set('apilot.response_wrapper', 'result');

        $response = $this->postJson('api/posts', [
            'title' => 'New Post',
            'body'  => 'Content',
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $this->assertArrayHasKey('result', $data);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function testStringWrapperResultUpdateWrapsInResult(): void
    {
        config()->set('apilot.response_wrapper', 'result');
        $post = Post::factory()->create();

        $response = $this->putJson("api/posts/{$post->id}", [
            'title'  => 'Updated',
            'status' => 'draft',
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('result', $data);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function testStringWrapperPayloadShowWrapsInPayload(): void
    {
        config()->set('apilot.response_wrapper', 'payload');
        $post = Post::factory()->create();

        $response = $this->getJson("api/posts/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('payload', $data);
        $this->assertArrayNotHasKey('data', $data);
    }

    // =========================================================================
    // Destroy — unaffected by all modes
    // =========================================================================

    public function testDestroyUnaffectedByNullWrapper(): void
    {
        config()->set('apilot.response_wrapper', null);
        $post = Post::factory()->create();

        $response = $this->deleteJson("api/posts/{$post->id}");

        $response->assertStatus(204);
        $this->assertEmpty($response->getContent());
    }

    public function testDestroyUnaffectedByEmptyArrayWrapper(): void
    {
        config()->set('apilot.response_wrapper', []);
        $post = Post::factory()->create();

        $response = $this->deleteJson("api/posts/{$post->id}");

        $response->assertStatus(204);
        $this->assertEmpty($response->getContent());
    }

    public function testDestroyUnaffectedByStringWrapper(): void
    {
        config()->set('apilot.response_wrapper', 'result');
        $post = Post::factory()->create();

        $response = $this->deleteJson("api/posts/{$post->id}");

        $response->assertStatus(204);
        $this->assertEmpty($response->getContent());
    }

    // =========================================================================
    // Error responses — unaffected by all modes
    // =========================================================================

    public function test404UnaffectedByEmptyArrayWrapper(): void
    {
        config()->set('apilot.response_wrapper', []);

        $response = $this->getJson('api/posts/99999');

        $response->assertStatus(404);
        $data = $response->json();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals(404, $data['error']['status']);
    }

    public function test404UnaffectedByStringWrapper(): void
    {
        config()->set('apilot.response_wrapper', 'result');

        $response = $this->getJson('api/posts/99999');

        $response->assertStatus(404);
        $data = $response->json();
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayNotHasKey('result', $data);
    }

    public function test422UnaffectedByEmptyArrayWrapper(): void
    {
        config()->set('apilot.response_wrapper', []);

        $response = $this->postJson('api/posts', []);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('errors', $data);
    }

    public function test422UnaffectedByStringWrapper(): void
    {
        config()->set('apilot.response_wrapper', 'result');

        $response = $this->postJson('api/posts', []);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayNotHasKey('result', $data);
    }

    // =========================================================================
    // Hooks
    // =========================================================================

    public function testHooksWorkWithEmptyArrayWrapper(): void
    {
        config()->set('apilot.response_wrapper', []);
        HookedPostController::resetHooks();

        $response = $this->postJson('api/hooked-posts', [
            'title' => 'Test Post',
            'body'  => 'Content',
        ]);

        $response->assertStatus(201);
        $this->assertContains('beforeStore', HookedPostController::$hooksCalled);
        $this->assertContains('afterStore', HookedPostController::$hooksCalled);
        $this->assertContains('transformResponse:store', HookedPostController::$hooksCalled);

        $data = $response->json();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function testHooksWorkWithNamedWrapper(): void
    {
        config()->set('apilot.response_wrapper', 'result');
        HookedPostController::resetHooks();

        $response = $this->postJson('api/hooked-posts', [
            'title' => 'Test Post',
            'body'  => 'Content',
        ]);

        $response->assertStatus(201);
        $this->assertContains('beforeStore', HookedPostController::$hooksCalled);
        $this->assertContains('afterStore', HookedPostController::$hooksCalled);
        $this->assertContains('transformResponse:store', HookedPostController::$hooksCalled);

        $data = $response->json();
        $this->assertArrayHasKey('result', $data);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function testTransformResponseHookCalledForAllModes(): void
    {
        foreach ([null, [], 'result'] as $wrapper) {
            HookedPostController::resetHooks();
            config()->set('apilot.response_wrapper', $wrapper);

            $this->postJson('api/hooked-posts', [
                'title' => 'Test Post',
                'body'  => 'Content',
            ]);

            $this->assertContains(
                'transformResponse:store',
                HookedPostController::$hooksCalled,
                "transformResponse was not called for wrapper=" . json_encode($wrapper)
            );
        }
    }

    // =========================================================================
    // ServiceCrudController
    // =========================================================================

    public function testServiceControllerEmptyArrayWrapperShow(): void
    {
        config()->set('apilot.response_wrapper', []);
        $tag = app(TagService::class)->create(['name' => 'Laravel', 'slug' => 'laravel']);

        $response = $this->getJson("api/tags/{$tag->id}");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function testServiceControllerEmptyArrayWrapperIndex(): void
    {
        config()->set('apilot.response_wrapper', []);

        $response = $this->getJson('api/tags');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('items', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertArrayNotHasKey('meta', $data);
        $this->assertArrayNotHasKey('links', $data);
    }

    public function testServiceControllerNamedWrapperShow(): void
    {
        config()->set('apilot.response_wrapper', 'result');
        $tag = app(TagService::class)->create(['name' => 'Laravel', 'slug' => 'laravel']);

        $response = $this->getJson("api/tags/{$tag->id}");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('result', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertEquals($tag->id, $data['result']['id']);
    }

    public function testServiceControllerNamedWrapperIndex(): void
    {
        config()->set('apilot.response_wrapper', 'result');

        $response = $this->getJson('api/tags');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('result', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('links', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertIsArray($data['result']);
    }

    public function testServiceControllerNullWrapperIndex(): void
    {
        config()->set('apilot.response_wrapper', null);

        $response = $this->getJson('api/tags');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('links', $data);
        $this->assertIsArray($data['data']);
    }

    // =========================================================================
    // OpenAPI Spec
    // =========================================================================

    public function testOpenApiSpecReflectsNamedWrapper(): void
    {
        config()->set('apilot.response_wrapper', 'result');

        $this->app->make(RouteRegistry::class)->clear();
        CrudRouteRegistrar::resource('posts', PostControllerFixture::class)
            ->only(['show', 'index'])
            ->register();

        $spec       = $this->app->make(OpenApiGenerator::class)->generate();
        $showSchema = $spec['paths']['/api/posts/{id}']['get']['responses']['200']['content']['application/json']['schema'];
        $indexSchema = $spec['paths']['/api/posts']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertArrayHasKey('result', $showSchema['properties']);
        $this->assertArrayNotHasKey('data', $showSchema['properties'] ?? []);
        $this->assertArrayHasKey('result', $indexSchema['properties']);
        $this->assertArrayNotHasKey('data', $indexSchema['properties']);
    }

    public function testOpenApiSpecReflectsEmptyArrayWrapper(): void
    {
        config()->set('apilot.response_wrapper', []);

        $this->app->make(RouteRegistry::class)->clear();
        CrudRouteRegistrar::resource('posts', PostControllerFixture::class)
            ->only(['show', 'index'])
            ->register();

        $spec       = $this->app->make(OpenApiGenerator::class)->generate();
        $showSchema = $spec['paths']['/api/posts/{id}']['get']['responses']['200']['content']['application/json']['schema'];
        $indexSchema = $spec['paths']['/api/posts']['get']['responses']['200']['content']['application/json']['schema'];

        // Show: direct $ref (no wrapper properties)
        $this->assertArrayHasKey('$ref', $showSchema);
        $this->assertArrayNotHasKey('properties', $showSchema);

        // Index: plain array schema
        $this->assertEquals('array', $indexSchema['type']);
        $this->assertArrayHasKey('items', $indexSchema);
        $this->assertArrayNotHasKey('properties', $indexSchema);
    }

    public function testOpenApiSpecReflectsNullWrapper(): void
    {
        config()->set('apilot.response_wrapper', null);

        $this->app->make(RouteRegistry::class)->clear();
        CrudRouteRegistrar::resource('posts', PostControllerFixture::class)
            ->only(['show', 'index'])
            ->register();

        $spec       = $this->app->make(OpenApiGenerator::class)->generate();
        $showSchema = $spec['paths']['/api/posts/{id}']['get']['responses']['200']['content']['application/json']['schema'];
        $indexSchema = $spec['paths']['/api/posts']['get']['responses']['200']['content']['application/json']['schema'];

        // Show: "data" wrapper (Laravel default)
        $this->assertArrayHasKey('data', $showSchema['properties']);

        // Index: "data" key
        $this->assertArrayHasKey('data', $indexSchema['properties']);
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function testEmptyStringFallsBackToLaravelDefault(): void
    {
        config()->set('apilot.response_wrapper', '');
        $post = Post::factory()->create();

        $response = $this->getJson("api/posts/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json();
        // Falls back to 'laravel' mode → Laravel adds "data" wrapper
        $this->assertArrayHasKey('data', $data);
    }

    public function testNumericValueFallsBackToLaravelDefault(): void
    {
        config()->set('apilot.response_wrapper', 123);
        $post = Post::factory()->create();

        $response = $this->getJson("api/posts/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json();
        // Falls back to 'laravel' mode → Laravel adds "data" wrapper
        $this->assertArrayHasKey('data', $data);
    }

    public function testNonEmptyArrayFallsBackToLaravelDefault(): void
    {
        config()->set('apilot.response_wrapper', ['data']);
        $post = Post::factory()->create();

        $response = $this->getJson("api/posts/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json();
        // Falls back to 'laravel' mode → Laravel adds "data" wrapper
        $this->assertArrayHasKey('data', $data);
    }
}
