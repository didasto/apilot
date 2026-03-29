<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature;

use Didasto\Apilot\Tests\Fixtures\Controllers\TagController;
use Didasto\Apilot\Tests\Fixtures\Services\TagService;
use Didasto\Apilot\Tests\TestCase;

class ServiceCrudControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        TagService::reset();
    }

    public function registerTestRoutes(): void
    {
        parent::registerTestRoutes();

        $this->app['router']->get('api/tags', [TagController::class, 'index']);
        $this->app['router']->get('api/tags/{id}', [TagController::class, 'show']);
        $this->app['router']->post('api/tags', [TagController::class, 'store']);
        $this->app['router']->put('api/tags/{id}', [TagController::class, 'update']);
        $this->app['router']->delete('api/tags/{id}', [TagController::class, 'destroy']);
    }

    public function testIndexReturnsEmptyList(): void
    {
        $response = $this->getJson('api/tags');

        $response->assertStatus(200);
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('meta.total', 0);
    }

    public function testIndexReturnsPaginatedResults(): void
    {
        $service = app(TagService::class);

        for ($i = 1; $i <= 20; $i++) {
            $service->create(['name' => "Tag {$i}", 'slug' => "tag-{$i}"]);
        }

        $response = $this->getJson('api/tags');

        $response->assertStatus(200);
        $response->assertJsonCount(15, 'data');
        $response->assertJsonPath('meta.total', 20);
        $response->assertJsonPath('meta.per_page', 15);
    }

    public function testIndexRespectsPerPageParameter(): void
    {
        $service = app(TagService::class);

        for ($i = 1; $i <= 10; $i++) {
            $service->create(['name' => "Tag {$i}", 'slug' => "tag-{$i}"]);
        }

        $response = $this->getJson('api/tags?per_page=5');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.per_page', 5);
    }

    public function testIndexWithFiltering(): void
    {
        $service = app(TagService::class);
        $service->create(['name' => 'Laravel Framework', 'slug' => 'laravel-framework']);
        $service->create(['name' => 'Vue.js', 'slug' => 'vuejs']);
        $service->create(['name' => 'Laravel Livewire', 'slug' => 'laravel-livewire']);

        $response = $this->getJson('api/tags?filter[name]=Laravel');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 2);
    }

    public function testIndexWithSorting(): void
    {
        $service = app(TagService::class);
        $service->create(['name' => 'Zebra', 'slug' => 'zebra']);
        $service->create(['name' => 'Apple', 'slug' => 'apple']);
        $service->create(['name' => 'Mango', 'slug' => 'mango']);

        $response = $this->getJson('api/tags?sort=name');

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertSame(['Apple', 'Mango', 'Zebra'], $names);
    }

    public function testIndexWithDescendingSorting(): void
    {
        $service = app(TagService::class);
        $service->create(['name' => 'Zebra', 'slug' => 'zebra']);
        $service->create(['name' => 'Apple', 'slug' => 'apple']);
        $service->create(['name' => 'Mango', 'slug' => 'mango']);

        $response = $this->getJson('api/tags?sort=-name');

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertSame(['Zebra', 'Mango', 'Apple'], $names);
    }

    public function testShowReturnsItem(): void
    {
        $service = app(TagService::class);
        $tag = $service->create(['name' => 'Laravel', 'slug' => 'laravel']);

        $response = $this->getJson("api/tags/{$tag->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $tag->id);
        $response->assertJsonPath('data.name', 'Laravel');
        $response->assertJsonPath('data.slug', 'laravel');
    }

    public function testShowReturns404ForMissingItem(): void
    {
        $response = $this->getJson('api/tags/9999');

        $response->assertStatus(404);
        $response->assertJsonPath('error.status', 404);
    }

    public function testStoreCreatesItem(): void
    {
        $response = $this->postJson('api/tags', [
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Laravel');
        $response->assertJsonPath('data.slug', 'laravel');

        $service = app(TagService::class);
        $this->assertNotNull($service->find(1));
    }

    public function testStoreValidatesInput(): void
    {
        $response = $this->postJson('api/tags', [
            'slug' => 'laravel',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function testUpdateModifiesItem(): void
    {
        $service = app(TagService::class);
        $tag = $service->create(['name' => 'Old Name', 'slug' => 'old-name']);

        $response = $this->putJson("api/tags/{$tag->id}", [
            'name' => 'New Name',
            'slug' => 'new-name',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'New Name');

        $updated = $service->find($tag->id);
        $this->assertSame('New Name', $updated->name);
    }

    public function testUpdateReturns404ForMissingItem(): void
    {
        $response = $this->putJson('api/tags/9999', [
            'name' => 'Some Name',
            'slug' => 'some-name',
        ]);

        $response->assertStatus(404);
    }

    public function testDestroyDeletesItem(): void
    {
        $service = app(TagService::class);
        $tag = $service->create(['name' => 'Laravel', 'slug' => 'laravel']);

        $response = $this->deleteJson("api/tags/{$tag->id}");

        $response->assertStatus(204);
        $this->assertNull($service->find($tag->id));
    }

    public function testDestroyReturns404ForMissingItem(): void
    {
        $response = $this->deleteJson('api/tags/9999');

        $response->assertStatus(404);
    }

    public function testIndexResponseFormatMatchesModelController(): void
    {
        $service = app(TagService::class);
        $service->create(['name' => 'Laravel', 'slug' => 'laravel']);

        $response = $this->getJson('api/tags');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                ['id', 'name', 'slug', 'created_at'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);
    }
}
