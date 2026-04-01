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
    // Tests 1–2: Default wrapper 'data'
    // =========================================================================

    public function testDefaultWrapperIsData(): void
    {
        config()->set('apilot.response_wrapper', 'data');

        $response = $this->postJson('api/posts', [
            'title' => 'Test Post',
            'body'  => 'Some content',
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertCount(1, array_keys($data));
    }

    public function testDefaultWrapperOnIndex(): void
    {
        config()->set('apilot.response_wrapper', 'data');

        $response = $this->getJson('api/posts');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('links', $data);
        $this->assertIsArray($data['data']);
    }

    // =========================================================================
    // Tests 3–4: Custom wrapper key 'result'
    // =========================================================================

    public function testCustomWrapperKey(): void
    {
        config()->set('apilot.response_wrapper', 'result');

        $response = $this->postJson('api/posts', [
            'title' => 'Test Post',
            'body'  => 'Some content',
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $this->assertArrayHasKey('result', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertCount(1, array_keys($data));
    }

    public function testCustomWrapperKeyOnIndex(): void
    {
        config()->set('apilot.response_wrapper', 'result');

        $response = $this->getJson('api/posts');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('result', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('links', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertIsArray($data['result']);
    }

    // =========================================================================
    // Tests 5–10: null wrapper
    // =========================================================================

    public function testNullWrapperRemovesWrappingOnShow(): void
    {
        config()->set('apilot.response_wrapper', null);
        $post = Post::factory()->create(['title' => 'Test Post']);

        $response = $this->getJson("api/posts/{$post->id}");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertEquals($post->id, $data['id']);
    }

    public function testNullWrapperRemovesWrappingOnStore(): void
    {
        config()->set('apilot.response_wrapper', null);

        $response = $this->postJson('api/posts', [
            'title' => 'New Post',
            'body'  => 'Some content',
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertEquals('New Post', $data['title']);
    }

    public function testNullWrapperRemovesWrappingOnUpdate(): void
    {
        config()->set('apilot.response_wrapper', null);
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

    public function testNullWrapperUsesItemsKeyOnIndex(): void
    {
        config()->set('apilot.response_wrapper', null);

        $response = $this->getJson('api/posts');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('links', $data);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertIsArray($data['items']);
    }

    public function testNullWrapperIndexMetaIsCorrect(): void
    {
        config()->set('apilot.response_wrapper', null);
        Post::factory()->count(20)->create();

        $response = $this->getJson('api/posts?per_page=5');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 20);
        $response->assertJsonPath('meta.last_page', 4);
        $response->assertJsonPath('meta.per_page', 5);
        $response->assertJsonPath('meta.current_page', 1);
    }

    public function testNullWrapperIndexLinksAreCorrect(): void
    {
        config()->set('apilot.response_wrapper', null);
        Post::factory()->count(20)->create();

        $response = $this->getJson('api/posts?per_page=5&page=2');

        $response->assertStatus(200);
        $this->assertNotNull($response->json('links.prev'));
        $this->assertNotNull($response->json('links.next'));
    }

    // =========================================================================
    // Test 11: Destroy unaffected by wrapper
    // =========================================================================

    public function testDestroyResponseUnaffectedByWrapper(): void
    {
        foreach ([null, 'data', 'result'] as $wrapper) {
            config()->set('apilot.response_wrapper', $wrapper);
            $post = Post::factory()->create();

            $response = $this->deleteJson("api/posts/{$post->id}");

            $response->assertStatus(204);
            $this->assertEmpty($response->getContent(), "Destroy body should be empty for wrapper={$wrapper}");
        }
    }

    // =========================================================================
    // Tests 12–14: ServiceCrudController
    // =========================================================================

    public function testWrapperWorksWithServiceController(): void
    {
        config()->set('apilot.response_wrapper', null);

        $response = $this->getJson('api/tags');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('links', $data);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function testWrapperWorksWithServiceControllerShow(): void
    {
        config()->set('apilot.response_wrapper', null);
        $tag = app(TagService::class)->create(['name' => 'Test Tag', 'slug' => 'test-tag']);

        $response = $this->getJson("api/tags/{$tag->id}");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function testCustomWrapperWorksWithServiceController(): void
    {
        config()->set('apilot.response_wrapper', 'result');

        $response = $this->postJson('api/tags', [
            'name' => 'Test Tag',
            'slug' => 'test-tag',
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $this->assertArrayHasKey('result', $data);
        $this->assertArrayNotHasKey('data', $data);
    }

    // =========================================================================
    // Tests 15–16: Error responses unaffected
    // =========================================================================

    public function testWrapperDoesNotAffectErrorResponses(): void
    {
        config()->set('apilot.response_wrapper', null);

        $response = $this->getJson('api/posts/99999');

        $response->assertStatus(404);
        $data = $response->json();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals(404, $data['error']['status']);
    }

    public function testWrapperDoesNotAffectValidationErrors(): void
    {
        config()->set('apilot.response_wrapper', null);

        $response = $this->postJson('api/posts', []); // missing required 'title'

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
    }

    // =========================================================================
    // Tests 17–19: Hooks
    // =========================================================================

    public function testHooksStillWorkWithNullWrapper(): void
    {
        config()->set('apilot.response_wrapper', null);
        HookedPostController::resetHooks();

        $response = $this->postJson('api/hooked-posts', [
            'title' => 'Test Post',
            'body'  => 'Some content',
        ]);

        $response->assertStatus(201);
        $this->assertContains('beforeStore', HookedPostController::$hooksCalled);
        $this->assertContains('afterStore', HookedPostController::$hooksCalled);
        $this->assertContains('transformResponse:store', HookedPostController::$hooksCalled);

        $data = $response->json();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function testHooksStillWorkWithCustomWrapper(): void
    {
        config()->set('apilot.response_wrapper', 'result');
        HookedPostController::resetHooks();

        $response = $this->postJson('api/hooked-posts', [
            'title' => 'Test Post',
            'body'  => 'Some content',
        ]);

        $response->assertStatus(201);
        $this->assertContains('beforeStore', HookedPostController::$hooksCalled);
        $this->assertContains('afterStore', HookedPostController::$hooksCalled);
        $this->assertContains('transformResponse:store', HookedPostController::$hooksCalled);

        $data = $response->json();
        $this->assertArrayHasKey('result', $data);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function testTransformResponseHookReceivesUnwrappedData(): void
    {
        HookedPostController::resetHooks();
        config()->set('apilot.response_wrapper', null);

        $this->postJson('api/hooked-posts', [
            'title' => 'Post with null wrapper',
            'body'  => 'Content',
        ]);

        $nullWrapperData = HookedPostController::$lastTransformResponseData;

        HookedPostController::resetHooks();
        config()->set('apilot.response_wrapper', 'data');

        $this->postJson('api/hooked-posts', [
            'title' => 'Post with data wrapper',
            'body'  => 'Content',
        ]);

        $dataWrapperData = HookedPostController::$lastTransformResponseData;

        // Both configs must produce the same data format in transformResponse
        $this->assertIsArray($nullWrapperData);
        $this->assertIsArray($dataWrapperData);
        $this->assertArrayHasKey('id', $nullWrapperData);
        $this->assertArrayHasKey('id', $dataWrapperData);
        $this->assertArrayNotHasKey('data', $nullWrapperData);
        $this->assertArrayNotHasKey('data', $dataWrapperData);
        $this->assertArrayNotHasKey('result', $nullWrapperData);
    }

    // =========================================================================
    // Tests 20–22: OpenAPI Spec
    // =========================================================================

    public function testOpenApiSpecReflectsWrapperConfig(): void
    {
        config()->set('apilot.response_wrapper', 'result');

        $this->app->make(RouteRegistry::class)->clear();
        CrudRouteRegistrar::resource('posts', PostControllerFixture::class)
            ->only(['show'])
            ->register();

        $spec       = $this->app->make(OpenApiGenerator::class)->generate();
        $showSchema = $spec['paths']['/api/posts/{id}']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertArrayHasKey('properties', $showSchema);
        $this->assertArrayHasKey('result', $showSchema['properties']);
        $this->assertArrayNotHasKey('data', $showSchema['properties']);
    }

    public function testOpenApiSpecReflectsNullWrapperOnShow(): void
    {
        config()->set('apilot.response_wrapper', null);

        $this->app->make(RouteRegistry::class)->clear();
        CrudRouteRegistrar::resource('posts', PostControllerFixture::class)
            ->only(['show'])
            ->register();

        $spec       = $this->app->make(OpenApiGenerator::class)->generate();
        $showSchema = $spec['paths']['/api/posts/{id}']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertArrayHasKey('$ref', $showSchema);
        $this->assertArrayNotHasKey('properties', $showSchema);
    }

    public function testOpenApiSpecReflectsNullWrapperOnIndex(): void
    {
        config()->set('apilot.response_wrapper', null);

        $this->app->make(RouteRegistry::class)->clear();
        CrudRouteRegistrar::resource('posts', PostControllerFixture::class)
            ->only(['index'])
            ->register();

        $spec        = $this->app->make(OpenApiGenerator::class)->generate();
        $indexSchema = $spec['paths']['/api/posts']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertArrayHasKey('properties', $indexSchema);
        $this->assertArrayHasKey('items', $indexSchema['properties']);
        $this->assertArrayHasKey('meta', $indexSchema['properties']);
        $this->assertArrayHasKey('links', $indexSchema['properties']);
        $this->assertArrayNotHasKey('data', $indexSchema['properties']);
    }
}
