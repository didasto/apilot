<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\EdgeCases;

use Didasto\Apilot\Tests\Fixtures\Controllers\MinimalController;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\TestCase;

class LargeDatasetTest extends TestCase
{
    public function registerTestRoutes(): void
    {
        $this->app['router']->get('api/minimal', [MinimalController::class, 'index']);
    }

    public function testIndexWith1000RecordsPaginatesCorrectly(): void
    {
        Post::factory()->count(1000)->create();

        $response = $this->getJson('api/minimal?per_page=50');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1000);
        $response->assertJsonPath('meta.last_page', 20);
        $response->assertJsonCount(50, 'data');
    }

    public function testIndexWithMaxPerPageRespected(): void
    {
        Post::factory()->count(1000)->create();

        $response = $this->getJson('api/minimal?per_page=9999');

        $response->assertStatus(200);
        // max_per_page config is 100
        $response->assertJsonPath('meta.per_page', 100);
        $response->assertJsonCount(100, 'data');
    }

    public function testIndexLastPageHasCorrectItemCount(): void
    {
        Post::factory()->count(1000)->create();

        $response = $this->getJson('api/minimal?per_page=30&page=34');

        $response->assertStatus(200);
        // 1000 / 30 = 33 full pages + 10 items on page 34
        $response->assertJsonCount(10, 'data');
    }

    public function testIndexBeyondLastPageReturnsEmptyData(): void
    {
        Post::factory()->count(1000)->create();

        $response = $this->getJson('api/minimal?page=9999');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 1000);
    }
}
