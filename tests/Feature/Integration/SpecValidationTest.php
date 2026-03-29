<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\Integration;

use Didasto\Apilot\OpenApi\OpenApiGenerator;
use Didasto\Apilot\OpenApi\SpecValidator;
use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\Routing\RouteEntry;
use Didasto\Apilot\Routing\RouteRegistry;
use Didasto\Apilot\Tests\Fixtures\Controllers\MinimalController;
use Didasto\Apilot\Tests\Fixtures\Controllers\PostController;
use Didasto\Apilot\Tests\Fixtures\Controllers\TagController;
use Orchestra\Testbench\TestCase;

class SpecValidationTest extends TestCase
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
        $app['config']->set('apilot.openapi.info.title', 'Test API');
        $app['config']->set('apilot.openapi.info.version', '1.0.0');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(RouteRegistry::class)->clear();
    }

    protected function generator(): OpenApiGenerator
    {
        return $this->app->make(OpenApiGenerator::class);
    }

    protected function validator(): SpecValidator
    {
        return $this->app->make(SpecValidator::class);
    }

    protected function registerRoutes(): void
    {
        $registry = $this->app->make(RouteRegistry::class);
        $registry->register(new RouteEntry(
            resourceName: 'posts',
            controllerClass: PostController::class,
            actions: ['index', 'show', 'store', 'update', 'destroy'],
            middleware: ['api'],
            prefix: 'api',
        ));
        $registry->register(new RouteEntry(
            resourceName: 'tags',
            controllerClass: TagController::class,
            actions: ['index', 'show'],
            middleware: ['api'],
            prefix: 'api',
        ));
    }

    public function testGeneratedSpecPassesInternalValidation(): void
    {
        $this->registerRoutes();

        $spec = $this->generator()->generate();
        $result = $this->validator()->validate($spec);

        $this->assertTrue($result['valid'], implode(', ', $result['errors']));
        $this->assertEmpty($result['errors']);
    }

    public function testGeneratedSpecHasNoOrphanedRefs(): void
    {
        $this->registerRoutes();

        $spec = $this->generator()->generate();
        $result = $this->validator()->validate($spec);

        $orphanErrors = array_filter($result['errors'], fn (string $e) => str_contains($e, 'Broken $ref'));

        $this->assertEmpty($orphanErrors, 'Spec contains orphaned $ref references: ' . implode(', ', $orphanErrors));
    }

    public function testGeneratedSpecHasConsistentOperationIds(): void
    {
        $this->registerRoutes();

        $spec = $this->generator()->generate();

        $operationIds = [];
        foreach ($spec['paths'] as $pathItem) {
            foreach ($pathItem as $method => $operation) {
                if (!is_array($operation) || !isset($operation['operationId'])) {
                    continue;
                }
                $opId = $operation['operationId'];
                $this->assertNotContains($opId, $operationIds, "Duplicate operationId: {$opId}");
                $operationIds[] = $opId;
            }
        }
    }

    public function testSpecWithNoRegisteredRoutesIsStillValid(): void
    {
        // Registry is already empty from setUp()
        $spec = $this->generator()->generate();
        $result = $this->validator()->validate($spec);

        $this->assertTrue($result['valid'], implode(', ', $result['errors']));
        $this->assertSame([], $spec['paths']);
    }

    public function testArtisanValidateFlagCatchesErrors(): void
    {
        // Override SpecValidator to always return failure
        $this->app->instance(SpecValidator::class, new class extends SpecValidator {
            public function validate(array $spec): array
            {
                return [
                    'valid'  => false,
                    'errors' => ['Injected test error'],
                ];
            }
        });

        $result = $this->artisan('apilot:generate-spec', ['--validate' => true]);

        $result->assertExitCode(1);
    }
}
