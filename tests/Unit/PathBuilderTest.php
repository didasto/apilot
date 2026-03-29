<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Unit;

use Didasto\Apilot\Attributes\OpenApiMeta;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\OpenApi\PathBuilder;
use Didasto\Apilot\OpenApi\SchemaBuilder;
use Didasto\Apilot\Routing\RouteEntry;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\Fixtures\Requests\PostRequest;
use Didasto\Apilot\Tests\Fixtures\Resources\PostResource;
use Orchestra\Testbench\TestCase;

class PathBuilderTest extends TestCase
{
    protected PathBuilder $pathBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pathBuilder = new PathBuilder(new SchemaBuilder());
    }

    protected function getPackageProviders($app): array
    {
        return [\Didasto\Apilot\ApilotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('apilot.openapi.default_security', 'bearer');
    }

    public function testBuildPathsForAllActions(): void
    {
        $entry = $this->makeEntry(PathBuilderPostController::class, ['index', 'show', 'store', 'update', 'destroy']);
        $paths = $this->pathBuilder->buildPaths($entry);

        $this->assertArrayHasKey('/api/posts', $paths);
        $this->assertArrayHasKey('/api/posts/{id}', $paths);
        $this->assertArrayHasKey('get', $paths['/api/posts']);
        $this->assertArrayHasKey('post', $paths['/api/posts']);
        $this->assertArrayHasKey('get', $paths['/api/posts/{id}']);
        $this->assertArrayHasKey('put', $paths['/api/posts/{id}']);
        $this->assertArrayHasKey('delete', $paths['/api/posts/{id}']);
    }

    public function testBuildPathsForSubsetOfActions(): void
    {
        $entry = $this->makeEntry(PathBuilderPostController::class, ['index', 'show']);
        $paths = $this->pathBuilder->buildPaths($entry);

        $this->assertArrayHasKey('/api/posts', $paths);
        $this->assertArrayHasKey('/api/posts/{id}', $paths);
        $this->assertArrayHasKey('get', $paths['/api/posts']);
        $this->assertArrayNotHasKey('post', $paths['/api/posts']);
        $this->assertArrayHasKey('get', $paths['/api/posts/{id}']);
        $this->assertArrayNotHasKey('put', $paths['/api/posts/{id}']);
        $this->assertArrayNotHasKey('delete', $paths['/api/posts/{id}']);
    }

    public function testOperationIdsAreCorrect(): void
    {
        $entry = $this->makeEntry(PathBuilderPostController::class, ['index', 'show', 'store', 'update', 'destroy']);
        $paths = $this->pathBuilder->buildPaths($entry);

        $this->assertEquals('posts.index', $paths['/api/posts']['get']['operationId']);
        $this->assertEquals('posts.store', $paths['/api/posts']['post']['operationId']);
        $this->assertEquals('posts.show', $paths['/api/posts/{id}']['get']['operationId']);
        $this->assertEquals('posts.update', $paths['/api/posts/{id}']['put']['operationId']);
        $this->assertEquals('posts.destroy', $paths['/api/posts/{id}']['delete']['operationId']);
    }

    public function testTagsAreGeneratedFromResourceName(): void
    {
        $entry = $this->makeEntry(PathBuilderPostController::class, ['index']);
        $paths = $this->pathBuilder->buildPaths($entry);

        $this->assertEquals(['Posts'], $paths['/api/posts']['get']['tags']);
    }

    public function testFilterParametersAreGenerated(): void
    {
        $entry = $this->makeEntry(PathBuilderPostController::class, ['index']);
        $paths = $this->pathBuilder->buildPaths($entry);

        $parameters    = $paths['/api/posts']['get']['parameters'];
        $paramNames    = array_column($parameters, 'name');
        $this->assertContains('filter[status]', $paramNames);
    }

    public function testSortParameterDescriptionContainsAllowedFields(): void
    {
        $entry = $this->makeEntry(PathBuilderPostController::class, ['index']);
        $paths = $this->pathBuilder->buildPaths($entry);

        $parameters = $paths['/api/posts']['get']['parameters'];
        $sortParam  = null;
        foreach ($parameters as $param) {
            if ($param['name'] === 'sort') {
                $sortParam = $param;
                break;
            }
        }

        $this->assertNotNull($sortParam);
        $this->assertStringContainsString('title', $sortParam['description']);
        $this->assertStringContainsString('created_at', $sortParam['description']);
    }

    protected function makeEntry(string $controllerClass, array $actions): RouteEntry
    {
        return new RouteEntry(
            resourceName:    'posts',
            controllerClass: $controllerClass,
            actions:         $actions,
            middleware:      ['api'],
            prefix:          'api',
        );
    }
}

// ---------------------------------------------------------------------------
// Fixture-Klassen (nur für diesen Test)
// ---------------------------------------------------------------------------

class PathBuilderPostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    protected ?string $resourceClass = PostResource::class;

    protected array $allowedFilters = [
        'status' => AllowedFilter::EXACT,
    ];

    protected array $allowedSorts = ['title', 'created_at'];
}
