<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\Filtering;

use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\Filters\IdFilter;
use Didasto\Apilot\OpenApi\PathBuilder;
use Didasto\Apilot\OpenApi\SchemaBuilder;
use Didasto\Apilot\Routing\RouteEntry;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\ApilotServiceProvider;
use Orchestra\Testbench\TestCase;

// ---------------------------------------------------------------------------
// Fixture controllers for OpenAPI tests
// ---------------------------------------------------------------------------

class OpenApiSingleEnumController extends ModelCrudController
{
    protected string $model = Post::class;

    protected array $allowedFilters = [
        'status' => AllowedFilter::EQUALS,
    ];
}

class OpenApiArrayFilterController extends ModelCrudController
{
    protected string $model = Post::class;

    protected array $allowedFilters = [
        'id' => [AllowedFilter::EQUALS, AllowedFilter::IN, AllowedFilter::GT],
    ];
}

class OpenApiFilterSetController extends ModelCrudController
{
    protected string $model = Post::class;

    protected array $allowedFilters = [
        'id' => IdFilter::class,
    ];
}

class OpenApiMixedFilterController extends ModelCrudController
{
    protected string $model = Post::class;

    protected array $allowedFilters = [
        'id'     => IdFilter::class,
        'status' => AllowedFilter::EQUALS,
        'title'  => [AllowedFilter::LIKE, AllowedFilter::EQUALS],
    ];
}

// ---------------------------------------------------------------------------

class FilterOpenApiTest extends TestCase
{
    protected PathBuilder $pathBuilder;

    protected function getPackageProviders($app): array
    {
        return [ApilotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('apilot.openapi.default_security', null);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pathBuilder = new PathBuilder(new SchemaBuilder());
    }

    private function makeEntry(string $controllerClass): RouteEntry
    {
        return new RouteEntry(
            resourceName:    'posts',
            controllerClass: $controllerClass,
            actions:         ['index'],
            middleware:      [],
            prefix:          'api',
        );
    }

    private function getParameterNames(array $paths): array
    {
        return array_column($paths['/api/posts']['get']['parameters'], 'name');
    }

    private function getParameters(array $paths): array
    {
        return $paths['/api/posts']['get']['parameters'];
    }

    public function testSingleEnumFilterProducesSingleParameter(): void
    {
        $paths = $this->pathBuilder->buildPaths($this->makeEntry(OpenApiSingleEnumController::class));
        $names = $this->getParameterNames($paths);

        $this->assertContains('filter[status]', $names);
        $this->assertNotContains('filter[status][eq]', $names);
    }

    public function testArrayFilterProducesMultipleParameters(): void
    {
        $paths = $this->pathBuilder->buildPaths($this->makeEntry(OpenApiArrayFilterController::class));
        $names = $this->getParameterNames($paths);

        $this->assertContains('filter[id][eq]', $names);
        $this->assertContains('filter[id][in]', $names);
        $this->assertContains('filter[id][gt]', $names);
        $this->assertNotContains('filter[id]', $names);
    }

    public function testFilterSetProducesParametersForAllOperators(): void
    {
        $paths = $this->pathBuilder->buildPaths($this->makeEntry(OpenApiFilterSetController::class));
        $names = $this->getParameterNames($paths);

        // IdFilter has: EQUALS, NOT_EQUALS, IN, NOT_IN
        $this->assertContains('filter[id][eq]', $names);
        $this->assertContains('filter[id][neq]', $names);
        $this->assertContains('filter[id][in]', $names);
        $this->assertContains('filter[id][notIn]', $names);
    }

    public function testFilterParameterDescriptionsAreCorrect(): void
    {
        $paths = $this->pathBuilder->buildPaths($this->makeEntry(OpenApiArrayFilterController::class));
        $params = $this->getParameters($paths);

        $gtParam = null;
        foreach ($params as $param) {
            if ($param['name'] === 'filter[id][gt]') {
                $gtParam = $param;
                break;
            }
        }

        $this->assertNotNull($gtParam);
        $this->assertStringContainsString('greater than', $gtParam['description']);
    }

    public function testFilterParameterForInHasExample(): void
    {
        $paths = $this->pathBuilder->buildPaths($this->makeEntry(OpenApiArrayFilterController::class));
        $params = $this->getParameters($paths);

        $inParam = null;
        foreach ($params as $param) {
            if ($param['name'] === 'filter[id][in]') {
                $inParam = $param;
                break;
            }
        }

        $this->assertNotNull($inParam);
        $this->assertSame('1,2,3', $inParam['schema']['example']);
    }

    public function testFilterParameterForBetweenHasExample(): void
    {
        $entry = new RouteEntry(
            resourceName:    'posts',
            controllerClass: OpenApiMixedWithBetweenController::class,
            actions:         ['index'],
            middleware:      [],
            prefix:          'api',
        );
        $paths = $this->pathBuilder->buildPaths($entry);
        $params = $this->getParameters($paths);

        $betweenParam = null;
        foreach ($params as $param) {
            if ($param['name'] === 'filter[price][between]') {
                $betweenParam = $param;
                break;
            }
        }

        $this->assertNotNull($betweenParam);
        $this->assertSame('2025-01-01,2025-12-31', $betweenParam['schema']['example']);
    }

    public function testMixedConfigProducesCorrectParameters(): void
    {
        $paths = $this->pathBuilder->buildPaths($this->makeEntry(OpenApiMixedFilterController::class));
        $names = $this->getParameterNames($paths);

        // id → IdFilter (4 operators: eq, neq, in, notIn)
        $this->assertContains('filter[id][eq]', $names);
        $this->assertContains('filter[id][neq]', $names);
        $this->assertContains('filter[id][in]', $names);
        $this->assertContains('filter[id][notIn]', $names);

        // status → single EQUALS → filter[status] (no suffix)
        $this->assertContains('filter[status]', $names);
        $this->assertNotContains('filter[status][eq]', $names);

        // title → array of 2 → filter[title][like], filter[title][eq]
        $this->assertContains('filter[title][like]', $names);
        $this->assertContains('filter[title][eq]', $names);
    }
}

// Additional fixture for between test
class OpenApiMixedWithBetweenController extends ModelCrudController
{
    protected string $model = Post::class;

    protected array $allowedFilters = [
        'price' => [AllowedFilter::GTE, AllowedFilter::LTE, AllowedFilter::BETWEEN],
    ];
}
