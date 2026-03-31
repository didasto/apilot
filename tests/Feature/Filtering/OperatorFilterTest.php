<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\Filtering;

use Didasto\Apilot\Tests\Fixtures\Controllers\OperatorFilterPostController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\TestCase;

class OperatorFilterTest extends TestCase
{
    public function registerTestRoutes(): void
    {
        parent::registerTestRoutes();

        $this->app['router']->get('api/operator-posts', [OperatorFilterPostController::class, 'index']);
    }

    public function testEqualsFilter(): void
    {
        Post::factory()->create(['status' => 'published']);
        Post::factory()->create(['status' => 'draft']);
        Post::factory()->create(['status' => 'published']);

        $response = $this->getJson('api/operator-posts?filter[status][eq]=published');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertSame('published', $item['status']);
        }
    }

    public function testNotEqualsFilter(): void
    {
        Post::factory()->create(['status' => 'published']);
        Post::factory()->create(['status' => 'draft']);
        Post::factory()->create(['status' => 'draft']);

        $response = $this->getJson('api/operator-posts?filter[status][neq]=draft');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
    }

    public function testInFilter(): void
    {
        Post::factory()->create(['status' => 'published']);
        Post::factory()->create(['status' => 'archived']);
        Post::factory()->create(['status' => 'draft']);

        $response = $this->getJson('api/operator-posts?filter[status][in]=published,archived');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }

    public function testNotInFilter(): void
    {
        Post::factory()->create(['status' => 'published']);
        Post::factory()->create(['status' => 'draft']);
        Post::factory()->create(['status' => 'archived']);

        $response = $this->getJson('api/operator-posts?filter[status][notIn]=draft,archived');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $this->assertSame('published', $response->json('data.0.status'));
    }

    public function testGreaterThanFilter(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Post::factory()->create();
        }

        $response = $this->getJson('api/operator-posts?filter[id][gt]=5&per_page=100');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 5);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        foreach ($ids as $id) {
            $this->assertGreaterThan(5, $id);
        }
    }

    public function testLessThanFilter(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Post::factory()->create();
        }

        $response = $this->getJson('api/operator-posts?filter[id][lt]=5&per_page=100');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 4);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        foreach ($ids as $id) {
            $this->assertLessThan(5, $id);
        }
    }

    public function testGreaterThanOrEqualFilter(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Post::factory()->create();
        }

        $response = $this->getJson('api/operator-posts?filter[id][gte]=5&per_page=100');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 6);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        foreach ($ids as $id) {
            $this->assertGreaterThanOrEqual(5, $id);
        }
    }

    public function testLessThanOrEqualFilter(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Post::factory()->create();
        }

        $response = $this->getJson('api/operator-posts?filter[id][lte]=5&per_page=100');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 5);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        foreach ($ids as $id) {
            $this->assertLessThanOrEqual(5, $id);
        }
    }

    public function testLikeFilter(): void
    {
        Post::factory()->create(['title' => 'Laravel Framework Guide']);
        Post::factory()->create(['title' => 'Vue.js Tutorial']);
        Post::factory()->create(['title' => 'Laravel Livewire Tips']);

        $response = $this->getJson('api/operator-posts?filter[title][like]=Laravel');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
        $titles = collect($response->json('data'))->pluck('title')->toArray();
        foreach ($titles as $title) {
            $this->assertStringContainsString('Laravel', $title);
        }
    }

    public function testNotLikeFilter(): void
    {
        Post::factory()->create(['title' => 'Laravel Framework Guide']);
        Post::factory()->create(['title' => 'Vue.js Tutorial']);
        Post::factory()->create(['title' => 'Laravel Livewire Tips']);

        $response = $this->getJson('api/operator-posts?filter[title][notLike]=Laravel');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $this->assertSame('Vue.js Tutorial', $response->json('data.0.title'));
    }

    public function testBetweenFilter(): void
    {
        Post::factory()->create(['created_at' => '2025-02-15 12:00:00']);
        Post::factory()->create(['created_at' => '2024-12-01 12:00:00']);
        Post::factory()->create(['created_at' => '2025-07-10 12:00:00']);

        $response = $this->getJson('api/operator-posts?filter[created_at][between]=2025-01-01,2025-06-30');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
    }

    public function testIsNullFilter(): void
    {
        Post::factory()->create(['body' => null]);
        Post::factory()->create(['body' => null]);
        Post::factory()->create(['body' => 'Some content']);

        $response = $this->getJson('api/operator-posts?filter[body][isNull]=1');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }

    public function testIsNotNullFilter(): void
    {
        Post::factory()->create(['body' => null]);
        Post::factory()->create(['body' => 'Content A']);
        Post::factory()->create(['body' => 'Content B']);

        $response = $this->getJson('api/operator-posts?filter[body][isNotNull]=1');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }

    public function testMultipleOperatorsOnSameField(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Post::factory()->create();
        }

        $response = $this->getJson('api/operator-posts?filter[id][gt]=5&filter[id][lt]=10&per_page=100');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 4);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        foreach ($ids as $id) {
            $this->assertGreaterThan(5, $id);
            $this->assertLessThan(10, $id);
        }
    }

    public function testMultipleOperatorsOnDifferentFields(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Post::factory()->create(['status' => 'published']);
        }
        for ($i = 1; $i <= 5; $i++) {
            Post::factory()->create(['status' => 'draft']);
        }

        $firstPublishedId = Post::where('status', 'published')->orderBy('id')->first()->id;

        $response = $this->getJson('api/operator-posts?filter[status][eq]=published&filter[id][gt]=' . ($firstPublishedId + 2) . '&per_page=100');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertSame('published', $item['status']);
            $this->assertGreaterThan($firstPublishedId + 2, $item['id']);
        }
    }

    public function testNotAllowedOperatorIsIgnored(): void
    {
        Post::factory()->count(3)->create();

        $response = $this->getJson('api/operator-posts?filter[id][like]=test');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 3);
    }

    public function testInFilterWithSingleValue(): void
    {
        Post::factory()->count(3)->create();
        $firstId = Post::orderBy('id')->first()->id;

        $response = $this->getJson('api/operator-posts?filter[id][in]=' . $firstId);

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $this->assertSame($firstId, $response->json('data.0.id'));
    }

    public function testBetweenFilterWithInvalidFormat(): void
    {
        Post::factory()->count(3)->create();

        $response = $this->getJson('api/operator-posts?filter[created_at][between]=only-one-value');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 3);
    }

    public function testBetweenFilterWithMoreThanTwoValues(): void
    {
        Post::factory()->count(3)->create();

        $response = $this->getJson('api/operator-posts?filter[created_at][between]=2025-01-01,2025-06-30,2025-12-31');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 3);
    }

    public function testLikeFilterEscapesWildcards(): void
    {
        Post::factory()->create(['title' => '100% done']);
        Post::factory()->create(['title' => '1000 ways to fail']);
        Post::factory()->create(['title' => '100 tips']);

        $response = $this->getJson('api/operator-posts?filter[title][like]=' . urlencode('100%'));

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $this->assertSame('100% done', $response->json('data.0.title'));
    }
}
