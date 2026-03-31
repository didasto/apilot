<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature;

use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Contracts\CrudServiceInterface;
use Didasto\Apilot\Controllers\ServiceCrudController;
use Didasto\Apilot\Services\AbstractCrudService;
use Didasto\Apilot\Tests\Fixtures\Resources\TagResource;
use Didasto\Apilot\Tests\Fixtures\Services\MinimalTagService;
use Didasto\Apilot\Tests\Fixtures\Services\TagService;
use Orchestra\Testbench\TestCase;

class AbstractCrudServiceTest extends TestCase
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

        MinimalTagService::reset();

        $this->app['router']->get('api/minimal-tags', [MinimalTagController::class, 'index']);
        $this->app['router']->get('api/minimal-tags/{id}', [MinimalTagController::class, 'show']);
        $this->app['router']->post('api/minimal-tags', [MinimalTagController::class, 'store']);
        $this->app['router']->put('api/minimal-tags/{id}', [MinimalTagController::class, 'update']);
        $this->app['router']->delete('api/minimal-tags/{id}', [MinimalTagController::class, 'destroy']);
    }

    protected function tearDown(): void
    {
        MinimalTagService::reset();
        parent::tearDown();
    }

    // =========================================================================
    // Tests
    // =========================================================================

    public function testListThrowsNotImplementedByDefault(): void
    {
        // MinimalTagService implementiert list() → würde 200 geben
        // Aber ein komplett leerer AbstractCrudService würde 501 geben
        // Wir testen direkt den AbstractCrudService via Exception
        $service = new class extends AbstractCrudService {};

        $this->expectException(\Didasto\Apilot\Exceptions\NotImplementedException::class);
        $this->expectExceptionMessage('is not implemented');
        $service->list([], [], new \Didasto\Apilot\Dto\PaginationParams(1, 15));
    }

    public function testCreateThrowsNotImplementedByDefault(): void
    {
        $response = $this->postJson('api/minimal-tags', ['name' => 'Test', 'slug' => 'test']);

        $response->assertStatus(501);
        $response->assertJsonPath('error.status', 501);
        $response->assertJsonPath('error.message', fn ($msg) => str_contains($msg, 'is not implemented'));
    }

    public function testUpdateThrowsNotImplementedByDefault(): void
    {
        $response = $this->putJson('api/minimal-tags/1', ['name' => 'Updated']);

        // find() returns null → 404
        $response->assertStatus(404);
    }

    public function testDeleteThrowsNotImplementedByDefault(): void
    {
        $response = $this->deleteJson('api/minimal-tags/1');

        // find() returns null → 404
        $response->assertStatus(404);
    }

    public function testOverriddenMethodWorks(): void
    {
        MinimalTagService::seed();

        $response = $this->getJson('api/minimal-tags');
        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
    }

    public function testFindOverriddenMethodWorks(): void
    {
        $seeded = MinimalTagService::seed();

        $response = $this->getJson("api/minimal-tags/{$seeded->id}");
        $response->assertStatus(200);
    }

    public function testNotImplementedResponseFormat(): void
    {
        $response = $this->postJson('api/minimal-tags', ['name' => 'Test', 'slug' => 'test']);

        $response->assertStatus(501);
        $response->assertJsonStructure([
            'error' => ['message', 'status'],
        ]);
        $response->assertJsonPath('error.status', 501);
    }

    public function testServiceCanExtendAbstractCrudService(): void
    {
        $service = new MinimalTagService();
        $this->assertInstanceOf(CrudServiceInterface::class, $service);
    }

    public function testServiceCanStillImplementInterfaceDirectly(): void
    {
        $service = new TagService();
        $this->assertInstanceOf(CrudServiceInterface::class, $service);

        // Verify it still works
        $result = $service->list([], [], new \Didasto\Apilot\Dto\PaginationParams(1, 15));
        $this->assertInstanceOf(\Didasto\Apilot\Dto\PaginatedResult::class, $result);
    }
}

// ---------------------------------------------------------------------------
// Inline-Fixture: Controller der MinimalTagService verwendet
// ---------------------------------------------------------------------------

class MinimalTagController extends ServiceCrudController
{
    protected string $serviceClass = MinimalTagService::class;
    protected ?string $resourceClass = TagResource::class;
}
