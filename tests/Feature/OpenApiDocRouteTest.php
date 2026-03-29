<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature;

use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Routing\RouteRegistry;
use Orchestra\Testbench\TestCase;

class OpenApiDocRouteTest extends TestCase
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
        $app['config']->set('apilot.prefix', 'api');
        $app['config']->set('apilot.openapi.enabled', true);
        $app['config']->set('apilot.openapi.path', 'doc');
        $app['config']->set('apilot.openapi.middleware', ['api']);
    }

    public function testDocRouteReturnsJsonResponse(): void
    {
        $response = $this->getJson('/api/doc');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function testDocRouteReturnsValidOpenApiSpec(): void
    {
        $response = $this->getJson('/api/doc');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('openapi', $data);
        $this->assertArrayHasKey('info', $data);
        $this->assertArrayHasKey('paths', $data);
        $this->assertArrayHasKey('components', $data);
        $this->assertEquals('3.0.3', $data['openapi']);
    }

    public function testArtisanCommandGeneratesFile(): void
    {
        $path = sys_get_temp_dir() . '/openapi-test-' . uniqid() . '.json';

        $this->app['config']->set('apilot.openapi.export_path', $path);

        $this->artisan('apilot:generate-spec')->assertSuccessful();

        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertNotFalse($content);

        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('openapi', $decoded);

        @unlink($path);
    }

    public function testArtisanCommandRespectsCustomPath(): void
    {
        $path = sys_get_temp_dir() . '/custom-spec-' . uniqid() . '.json';

        $this->artisan('apilot:generate-spec', ['--path' => $path])->assertSuccessful();

        $this->assertFileExists($path);

        $decoded = json_decode(file_get_contents($path), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('openapi', $decoded);

        @unlink($path);
    }

    public function testArtisanCommandStdoutOption(): void
    {
        $path = sys_get_temp_dir() . '/openapi-should-not-exist-' . uniqid() . '.json';

        $this->app['config']->set('apilot.openapi.export_path', $path);

        $this->artisan('apilot:generate-spec', ['--stdout' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('"openapi"');

        $this->assertFileDoesNotExist($path);
    }
}

/**
 * Test dass die Doc-Route deaktiviert werden kann.
 * Separate Klasse, da defineEnvironment vor dem Boot aufgerufen wird.
 */
class OpenApiDocRouteDisabledTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ApilotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('apilot.prefix', 'api');
        $app['config']->set('apilot.openapi.enabled', false);
        $app['config']->set('apilot.openapi.path', 'doc');
    }

    public function testDocRouteIsDisabledWhenConfigIsFalse(): void
    {
        $response = $this->get('/api/doc');
        $response->assertStatus(404);
    }
}

/**
 * Test dass die Doc-Route einen benutzerdefinierten Pfad unterstützt.
 */
class OpenApiDocRouteCustomPathTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ApilotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('apilot.prefix', 'api');
        $app['config']->set('apilot.openapi.enabled', true);
        $app['config']->set('apilot.openapi.path', 'documentation');
        $app['config']->set('apilot.openapi.middleware', ['api']);
    }

    public function testDocRouteRespectsCustomPath(): void
    {
        $response = $this->getJson('/api/documentation');
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('openapi', $data);
    }
}
