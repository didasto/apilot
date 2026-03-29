<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\Integration;

use Didasto\Apilot\Tests\Fixtures\Controllers\FullFeaturedController;
use Didasto\Apilot\Tests\Fixtures\Controllers\MinimalController;
use Didasto\Apilot\Tests\Fixtures\Controllers\TagController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Services\TagService;
use Didasto\Apilot\Tests\TestCase;
use Didasto\Apilot\OpenApi\OpenApiGenerator;
use Didasto\Apilot\Routing\RouteRegistry;
use Didasto\Apilot\Routing\RouteEntry;

class FullWorkflowTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        FullFeaturedController::resetHooks();
        TagService::reset();
    }

    public function registerTestRoutes(): void
    {
        // Model-based controller
        $this->app['router']->get('api/posts', [MinimalController::class, 'index']);
        $this->app['router']->get('api/posts/{id}', [MinimalController::class, 'show']);
        $this->app['router']->post('api/posts', [MinimalController::class, 'store']);
        $this->app['router']->put('api/posts/{id}', [MinimalController::class, 'update']);
        $this->app['router']->delete('api/posts/{id}', [MinimalController::class, 'destroy']);

        // Service-based controller
        $this->app['router']->get('api/tags', [TagController::class, 'index']);
        $this->app['router']->get('api/tags/{id}', [TagController::class, 'show']);
        $this->app['router']->post('api/tags', [TagController::class, 'store']);
        $this->app['router']->put('api/tags/{id}', [TagController::class, 'update']);
        $this->app['router']->delete('api/tags/{id}', [TagController::class, 'destroy']);

        // Full-featured controller
        $this->app['router']->get('api/full', [FullFeaturedController::class, 'index']);
        $this->app['router']->get('api/full/{id}', [FullFeaturedController::class, 'show']);
        $this->app['router']->post('api/full', [FullFeaturedController::class, 'store']);
        $this->app['router']->put('api/full/{id}', [FullFeaturedController::class, 'update']);
        $this->app['router']->delete('api/full/{id}', [FullFeaturedController::class, 'destroy']);
    }

    public function testCompleteModelCrudLifecycle(): void
    {
        // Store
        $storeResponse = $this->postJson('api/posts', [
            'title'  => 'My Post',
            'body'   => 'Content here',
            'status' => 'draft',
        ]);
        $storeResponse->assertStatus(201);
        $id = $storeResponse->json('data.id');
        $this->assertNotNull($id);

        // Show
        $showResponse = $this->getJson("api/posts/{$id}");
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('data.title', 'My Post');

        // Update
        $updateResponse = $this->putJson("api/posts/{$id}", [
            'title'  => 'Updated Post',
            'status' => 'published',
        ]);
        $updateResponse->assertStatus(200);
        $updateResponse->assertJsonPath('data.title', 'Updated Post');

        // Index — find it
        $indexResponse = $this->getJson('api/posts');
        $indexResponse->assertStatus(200);
        $indexResponse->assertJsonPath('meta.total', 1);

        // Destroy
        $destroyResponse = $this->deleteJson("api/posts/{$id}");
        $destroyResponse->assertStatus(204);

        // Show after destroy — 404
        $missingResponse = $this->getJson("api/posts/{$id}");
        $missingResponse->assertStatus(404);
    }

    public function testCompleteServiceCrudLifecycle(): void
    {
        // Store
        $storeResponse = $this->postJson('api/tags', [
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);
        $storeResponse->assertStatus(201);
        $id = $storeResponse->json('data.id');
        $this->assertNotNull($id);

        // Show
        $showResponse = $this->getJson("api/tags/{$id}");
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('data.name', 'Laravel');

        // Update
        $updateResponse = $this->putJson("api/tags/{$id}", [
            'name' => 'Laravel Framework',
            'slug' => 'laravel-framework',
        ]);
        $updateResponse->assertStatus(200);
        $updateResponse->assertJsonPath('data.name', 'Laravel Framework');

        // Index — find it
        $indexResponse = $this->getJson('api/tags');
        $indexResponse->assertStatus(200);
        $indexResponse->assertJsonPath('meta.total', 1);

        // Destroy
        $destroyResponse = $this->deleteJson("api/tags/{$id}");
        $destroyResponse->assertStatus(204);

        // Show after destroy — 404
        $missingResponse = $this->getJson("api/tags/{$id}");
        $missingResponse->assertStatus(404);
    }

    public function testModelControllerWithAllHooksActive(): void
    {
        FullFeaturedController::resetHooks();

        // Store
        $this->postJson('api/full', [
            'title'  => 'Hook Test',
            'status' => 'draft',
        ]);
        $this->assertContains('beforeStore', FullFeaturedController::$hooksCalled);
        $this->assertContains('afterStore', FullFeaturedController::$hooksCalled);
        $this->assertContains('transformResponse:store', FullFeaturedController::$hooksCalled);

        FullFeaturedController::resetHooks();

        // Index
        $indexResponse = $this->getJson('api/full');
        $indexResponse->assertStatus(200);
        $this->assertContains('modifyIndexQuery', FullFeaturedController::$hooksCalled);
        $this->assertContains('afterIndex', FullFeaturedController::$hooksCalled);
        $this->assertContains('transformResponse:index', FullFeaturedController::$hooksCalled);

        $post = Post::first();
        FullFeaturedController::resetHooks();

        // Show
        $this->getJson("api/full/{$post->id}");
        $this->assertContains('afterShow', FullFeaturedController::$hooksCalled);
        $this->assertContains('transformResponse:show', FullFeaturedController::$hooksCalled);

        FullFeaturedController::resetHooks();

        // Update
        $this->putJson("api/full/{$post->id}", [
            'title'  => 'Updated Hook Test',
            'status' => 'draft',
        ]);
        $this->assertContains('beforeUpdate', FullFeaturedController::$hooksCalled);
        $this->assertContains('afterUpdate', FullFeaturedController::$hooksCalled);

        FullFeaturedController::resetHooks();

        // Destroy
        $this->deleteJson("api/full/{$post->id}");
        $this->assertContains('beforeDestroy', FullFeaturedController::$hooksCalled);
        $this->assertContains('afterDestroy', FullFeaturedController::$hooksCalled);
    }

    public function testMixedControllersOnSameApp(): void
    {
        // Create a post via model controller
        $postResponse = $this->postJson('api/posts', [
            'title'  => 'Post One',
            'status' => 'draft',
        ]);
        $postResponse->assertStatus(201);
        $postId = $postResponse->json('data.id');

        // Create a tag via service controller
        $tagResponse = $this->postJson('api/tags', [
            'name' => 'Tag One',
            'slug' => 'tag-one',
        ]);
        $tagResponse->assertStatus(201);
        $tagId = $tagResponse->json('data.id');

        // Both controllers work independently
        $this->getJson("api/posts/{$postId}")->assertStatus(200);
        $this->getJson("api/tags/{$tagId}")->assertStatus(200);

        // Model controller index only returns posts, not tags
        $this->getJson('api/posts')->assertJsonPath('meta.total', 1);

        // Service controller index only returns tags, not posts
        $this->getJson('api/tags')->assertJsonPath('meta.total', 1);
    }

    public function testOpenApiSpecReflectsAllRegisteredRoutes(): void
    {
        $registry = $this->app->make(RouteRegistry::class);
        $registry->clear();

        // Register entries with different only() configurations
        $registry->register(new RouteEntry(
            resourceName: 'posts',
            controllerClass: MinimalController::class,
            actions: ['index', 'show', 'store', 'update', 'destroy'],
            middleware: ['api'],
            prefix: 'api',
        ));
        $registry->register(new RouteEntry(
            resourceName: 'tags',
            controllerClass: TagController::class,
            actions: ['index', 'show'],
            middleware: ['api'],
            prefix: 'api',
        ));

        $generator = $this->app->make(OpenApiGenerator::class);
        $spec = $generator->generate();

        // Posts should have all 5 paths
        $this->assertArrayHasKey('/api/posts', $spec['paths']);
        $this->assertArrayHasKey('/api/posts/{id}', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/api/posts']);
        $this->assertArrayHasKey('post', $spec['paths']['/api/posts']);
        $this->assertArrayHasKey('get', $spec['paths']['/api/posts/{id}']);
        $this->assertArrayHasKey('put', $spec['paths']['/api/posts/{id}']);
        $this->assertArrayHasKey('delete', $spec['paths']['/api/posts/{id}']);

        // Tags should only have index and show
        $this->assertArrayHasKey('get', $spec['paths']['/api/tags']);
        $this->assertArrayNotHasKey('post', $spec['paths']['/api/tags'] ?? []);
        $this->assertArrayHasKey('get', $spec['paths']['/api/tags/{id}']);
        $this->assertArrayNotHasKey('put', $spec['paths']['/api/tags/{id}'] ?? []);
        $this->assertArrayNotHasKey('delete', $spec['paths']['/api/tags/{id}'] ?? []);
    }
}
