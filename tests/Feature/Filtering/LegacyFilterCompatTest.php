<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\Filtering;

use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\Filters\IdFilter;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\TestCase;

class LegacyController extends ModelCrudController
{
    protected string $model = Post::class;

    protected array $allowedFilters = [
        'status' => AllowedFilter::EXACT,
        'title'  => AllowedFilter::PARTIAL,
    ];
}

class LegacyScopeController extends ModelCrudController
{
    protected string $model = Post::class;

    protected array $allowedFilters = [
        'status' => AllowedFilter::SCOPE,
    ];
}

class LegacyWithNewConfigController extends ModelCrudController
{
    protected string $model = Post::class;

    protected array $allowedFilters = [
        'id' => IdFilter::class,
    ];
}

class LegacyFilterCompatTest extends TestCase
{
    public function registerTestRoutes(): void
    {
        parent::registerTestRoutes();

        $this->app['router']->get('api/legacy-posts', [LegacyController::class, 'index']);
        $this->app['router']->get('api/legacy-scope-posts', [LegacyScopeController::class, 'index']);
        $this->app['router']->get('api/legacy-new-config-posts', [LegacyWithNewConfigController::class, 'index']);
    }

    public function testLegacyExactFilterStillWorks(): void
    {
        Post::factory()->create(['status' => 'published']);
        Post::factory()->create(['status' => 'draft']);

        $response = $this->getJson('api/legacy-posts?filter[status]=published');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $this->assertSame('published', $response->json('data.0.status'));
    }

    public function testLegacyPartialFilterStillWorks(): void
    {
        Post::factory()->create(['title' => 'Laravel Framework Guide']);
        Post::factory()->create(['title' => 'Vue.js Tutorial']);
        Post::factory()->create(['title' => 'Laravel Tips']);

        $response = $this->getJson('api/legacy-posts?filter[title]=Laravel');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }

    public function testLegacyScopeFilterStillWorks(): void
    {
        Post::factory()->create(['status' => 'published']);
        Post::factory()->create(['status' => 'draft']);
        Post::factory()->create(['status' => 'draft']);

        $response = $this->getJson('api/legacy-scope-posts?filter[status]=draft');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }

    public function testLegacyFormatWorksWithNewOperatorConfig(): void
    {
        Post::factory()->count(3)->create();
        $targetId = Post::orderBy('id')->first()->id;

        $response = $this->getJson('api/legacy-new-config-posts?filter[id]=' . $targetId);

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $this->assertSame($targetId, $response->json('data.0.id'));
    }

    public function testLegacyIgnoresDisallowedFilters(): void
    {
        Post::factory()->count(3)->create();

        $response = $this->getJson('api/legacy-posts?filter[unknown]=test');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 3);
    }
}
