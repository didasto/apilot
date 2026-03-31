<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature\Filtering;

use Didasto\Apilot\Tests\Fixtures\Controllers\OperatorFilterTagController;
use Didasto\Apilot\Tests\Fixtures\Services\OperatorFilterTagService;
use Didasto\Apilot\Tests\TestCase;

class ServiceFilterTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        OperatorFilterTagService::reset();
    }

    public function registerTestRoutes(): void
    {
        parent::registerTestRoutes();

        $this->app['router']->get('api/operator-tags', [OperatorFilterTagController::class, 'index']);
    }

    public function testServiceReceivesStructuredFilterArray(): void
    {
        $this->getJson('api/operator-tags?filter[name][like]=Laravel&filter[id][gt]=5');

        $this->assertSame('Laravel', OperatorFilterTagService::$lastFilters['name']['like']);
        $this->assertSame('5', OperatorFilterTagService::$lastFilters['id']['gt']);
    }

    public function testServiceReceivesLegacyFilterAsDefault(): void
    {
        $this->getJson('api/operator-tags?filter[name]=Laravel');

        $this->assertArrayHasKey('name', OperatorFilterTagService::$lastFilters);
        $this->assertArrayHasKey('eq', OperatorFilterTagService::$lastFilters['name']);
        $this->assertSame('Laravel', OperatorFilterTagService::$lastFilters['name']['eq']);
    }

    public function testServiceOnlyReceivesAllowedFilters(): void
    {
        $this->getJson('api/operator-tags?filter[unknown][eq]=test');

        $this->assertArrayNotHasKey('unknown', OperatorFilterTagService::$lastFilters);
    }

    public function testServiceOnlyReceivesAllowedOperators(): void
    {
        $this->getJson('api/operator-tags?filter[name][between]=a,z');

        $this->assertArrayNotHasKey('name', OperatorFilterTagService::$lastFilters);
    }
}
