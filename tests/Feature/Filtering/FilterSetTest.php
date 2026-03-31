<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\Filtering;

use Didasto\Apilot\Tests\Fixtures\Controllers\FilterSetPostController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\TestCase;

class FilterSetTest extends TestCase
{
    public function registerTestRoutes(): void
    {
        parent::registerTestRoutes();

        $this->app['router']->get('api/filterset-posts', [FilterSetPostController::class, 'index']);
    }

    public function testIdFilterSetAllowsEquals(): void
    {
        Post::factory()->count(3)->create();
        $targetId = Post::orderBy('id')->first()->id;

        $response = $this->getJson('api/filterset-posts?filter[id][eq]=' . $targetId);

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $this->assertSame($targetId, $response->json('data.0.id'));
    }

    public function testIdFilterSetAllowsIn(): void
    {
        Post::factory()->count(5)->create();
        $ids = Post::orderBy('id')->take(3)->pluck('id')->toArray();

        $response = $this->getJson('api/filterset-posts?filter[id][in]=' . implode(',', $ids));

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 3);
    }

    public function testIdFilterSetRejectsLike(): void
    {
        Post::factory()->count(3)->create();

        $response = $this->getJson('api/filterset-posts?filter[id][like]=test');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 3);
    }

    public function testTextFilterSetAllowsLike(): void
    {
        Post::factory()->create(['title' => 'Laravel Framework']);
        Post::factory()->create(['title' => 'Vue.js Guide']);

        $response = $this->getJson('api/filterset-posts?filter[title][like]=Laravel');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $this->assertSame('Laravel Framework', $response->json('data.0.title'));
    }

    public function testTextFilterSetAllowsIsNull(): void
    {
        Post::factory()->create(['title' => 'Has Title']);
        Post::factory()->create(['body' => null]);
        Post::factory()->create(['body' => null]);

        $response = $this->getJson('api/filterset-posts?filter[title][isNull]=1');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 0);
    }

    public function testNumericFilterSetAllowsBetween(): void
    {
        Post::factory()->create(['price' => 5.00]);
        Post::factory()->create(['price' => 50.00]);
        Post::factory()->create(['price' => 150.00]);

        $response = $this->getJson('api/filterset-posts?filter[price][between]=10,100');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
    }

    public function testDateFilterSetAllowsGte(): void
    {
        Post::factory()->create(['created_at' => '2024-06-01 00:00:00']);
        Post::factory()->create(['created_at' => '2025-01-01 00:00:00']);
        Post::factory()->create(['created_at' => '2025-06-01 00:00:00']);

        $response = $this->getJson('api/filterset-posts?filter[created_at][gte]=2025-01-01');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }

    public function testBooleanFilterSetAllowsEquals(): void
    {
        Post::factory()->create(['is_active' => true]);
        Post::factory()->create(['is_active' => false]);
        Post::factory()->create(['is_active' => true]);

        $response = $this->getJson('api/filterset-posts?filter[is_active][eq]=1');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }

    public function testBooleanFilterSetRejectsBetween(): void
    {
        Post::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('api/filterset-posts?filter[is_active][between]=0,1');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 3);
    }

    public function testCustomFilterSetWorks(): void
    {
        Post::factory()->create(['status' => 'active']);
        Post::factory()->create(['status' => 'pending']);
        Post::factory()->create(['status' => 'inactive']);

        $inResponse = $this->getJson('api/filterset-posts?filter[status][in]=active,pending');

        $inResponse->assertStatus(200);
        $inResponse->assertJsonPath('meta.total', 2);

        $likeResponse = $this->getJson('api/filterset-posts?filter[status][like]=act');

        $likeResponse->assertStatus(200);
        $likeResponse->assertJsonPath('meta.total', 3);
    }
}
