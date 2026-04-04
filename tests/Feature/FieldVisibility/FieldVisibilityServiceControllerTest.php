<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\FieldVisibility;

use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Controllers\ServiceCrudController;
use Didasto\Apilot\Contracts\CrudServiceInterface;
use Didasto\Apilot\Dto\PaginatedResult;
use Didasto\Apilot\Dto\PaginationParams;
use Didasto\Apilot\Tests\Fixtures\Services\SecretTagService;
use Orchestra\Testbench\TestCase;
use stdClass;

class FieldVisibilityServiceControllerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ApilotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        SecretTagService::reset();

        $this->app['router']->get('api/visible-tags', [VisibleServiceController::class, 'index']);
        $this->app['router']->get('api/visible-tags/{id}', [VisibleServiceController::class, 'show']);
        $this->app['router']->get('api/hidden-tags/{id}', [HiddenServiceController::class, 'show']);
        $this->app['router']->get('api/stdclass-tags/{id}', [StdClassServiceController::class, 'show']);
        $this->app['router']->get('api/array-tags/{id}', [ArrayServiceController::class, 'show']);
    }

    public function testVisibleFieldsWorksOnServiceController(): void
    {
        $service = app(SecretTagService::class);
        $tag = $service->create(['name' => 'PHP']);

        $response = $this->getJson("api/visible-tags/{$tag->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('secret', $data);
        $this->assertArrayNotHasKey('created_at', $data);
    }

    public function testHiddenFieldsWorksOnServiceController(): void
    {
        $service = app(SecretTagService::class);
        $tag = $service->create(['name' => 'PHP']);

        $response = $this->getJson("api/hidden-tags/{$tag->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayNotHasKey('secret', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
    }

    public function testServiceControllerWithStdClassItems(): void
    {
        $service = app(SecretTagService::class);
        $tag = $service->create(['name' => 'PHP']);

        // SecretTagService returns stdClass objects
        $item = $service->find($tag->id);
        $this->assertInstanceOf(stdClass::class, $item);

        $response = $this->getJson("api/stdclass-tags/{$tag->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('secret', $data);
    }

    public function testServiceControllerWithArrayItems(): void
    {
        $service = app(ArrayItemService::class);
        $item = $service->create(['name' => 'Test', 'secret' => 'hidden']);

        $response = $this->getJson("api/array-tags/{$item['id']}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('secret', $data);
    }
}

// ---------------------------------------------------------------------------
// Inline Service Fixtures
// ---------------------------------------------------------------------------

class ArrayItemService implements CrudServiceInterface
{
    protected static array $items = [];
    protected static int $nextId = 1;

    public static function reset(): void
    {
        static::$items = [];
        static::$nextId = 1;
    }

    public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult
    {
        return new PaginatedResult(items: array_values(static::$items), total: count(static::$items), perPage: 15, currentPage: 1);
    }

    public function find(int|string $id): mixed
    {
        return static::$items[(int) $id] ?? null;
    }

    public function create(array $data): mixed
    {
        $id = static::$nextId++;
        $item = ['id' => $id, 'name' => $data['name'] ?? '', 'secret' => $data['secret'] ?? ''];
        static::$items[$id] = $item;
        return $item;
    }

    public function update(int|string $id, array $data): mixed
    {
        return static::$items[(int) $id] ?? null;
    }

    public function delete(int|string $id): bool
    {
        unset(static::$items[(int) $id]);
        return true;
    }
}

// ---------------------------------------------------------------------------
// Inline Controller Fixtures
// ---------------------------------------------------------------------------

class VisibleServiceController extends ServiceCrudController
{
    protected string $serviceClass = SecretTagService::class;
    protected array $visibleFields = ['id', 'name'];
}

class HiddenServiceController extends ServiceCrudController
{
    protected string $serviceClass = SecretTagService::class;
    protected array $hiddenFields = ['secret'];
}

class StdClassServiceController extends ServiceCrudController
{
    protected string $serviceClass = SecretTagService::class;
    protected array $visibleFields = ['id', 'name'];
}

class ArrayServiceController extends ServiceCrudController
{
    protected string $serviceClass = ArrayItemService::class;
    protected array $visibleFields = ['id', 'name'];
}
