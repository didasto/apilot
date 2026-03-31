<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\Filtering;

use Didasto\Apilot\Tests\Fixtures\Controllers\MixedFilterPostController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\TestCase;

class MixedFilterConfigTest extends TestCase
{
    public function registerTestRoutes(): void
    {
        parent::registerTestRoutes();

        $this->app['router']->get('api/mixed-posts', [MixedFilterPostController::class, 'index']);
    }

    public function testSingleEnumFilterWorks(): void
    {
        Post::factory()->create(['status' => 'published']);
        Post::factory()->create(['status' => 'draft']);

        $response = $this->getJson('api/mixed-posts?filter[status]=published');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $this->assertSame('published', $response->json('data.0.status'));
    }

    public function testSingleEnumFilterWithOperatorFormat(): void
    {
        Post::factory()->create(['status' => 'published']);
        Post::factory()->create(['status' => 'draft']);

        $response = $this->getJson('api/mixed-posts?filter[status][eq]=published');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $this->assertSame('published', $response->json('data.0.status'));
    }

    public function testSingleEnumRejectsOtherOperators(): void
    {
        Post::factory()->create(['status' => 'published']);
        Post::factory()->create(['status' => 'draft']);

        $response = $this->getJson('api/mixed-posts?filter[status][like]=pub');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }

    public function testArrayFilterFirstIsDefault(): void
    {
        Post::factory()->create(['title' => 'Laravel Framework Guide']);
        Post::factory()->create(['title' => 'Vue.js Tutorial']);
        Post::factory()->create(['title' => 'Laravel Livewire Tips']);

        $response = $this->getJson('api/mixed-posts?filter[title]=Laravel');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }

    public function testFilterSetAndSingleEnumAndArrayCanCoexist(): void
    {
        Post::factory()->create(['status' => 'published', 'title' => 'Test Post One']);
        Post::factory()->create(['status' => 'published', 'title' => 'Test Post Two']);
        Post::factory()->create(['status' => 'draft', 'title' => 'Test Post Three']);

        $firstId = Post::orderBy('id')->first()->id;
        $secondId = Post::orderBy('id')->skip(1)->first()->id;

        $response = $this->getJson(
            'api/mixed-posts?filter[id][in]=' . $firstId . ',' . $secondId . '&filter[status]=published&filter[title][like]=Test'
        );

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }

    public function testScopeFilterStillWorksInMixedConfig(): void
    {
        Post::factory()->create(['category' => 'tech']);
        Post::factory()->create(['category' => 'science']);
        Post::factory()->create(['category' => 'tech']);

        $response = $this->getJson('api/mixed-posts?filter[category]=tech');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }
}
