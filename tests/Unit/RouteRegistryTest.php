<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Unit;

use Didasto\Apilot\Routing\RouteEntry;
use Didasto\Apilot\Routing\RouteRegistry;
use PHPUnit\Framework\TestCase;

class RouteRegistryTest extends TestCase
{
    private RouteRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new RouteRegistry();
    }

    protected function makeEntry(string $resourceName = 'posts'): RouteEntry
    {
        return new RouteEntry(
            resourceName: $resourceName,
            controllerClass: 'App\\Http\\Controllers\\PostController',
            actions: ['index', 'show', 'store', 'update', 'destroy'],
            middleware: ['api'],
            prefix: 'api',
        );
    }

    public function testRegisterAddsEntry(): void
    {
        $entry = $this->makeEntry();
        $this->registry->register($entry);

        $this->assertCount(1, $this->registry->all());
        $this->assertSame($entry, $this->registry->all()[0]);
    }

    public function testClearRemovesAllEntries(): void
    {
        $this->registry->register($this->makeEntry('posts'));
        $this->registry->register($this->makeEntry('comments'));

        $this->registry->clear();

        $this->assertSame([], $this->registry->all());
    }

    public function testMultipleEntriesAreStored(): void
    {
        $this->registry->register($this->makeEntry('posts'));
        $this->registry->register($this->makeEntry('comments'));
        $this->registry->register($this->makeEntry('tags'));

        $this->assertCount(3, $this->registry->all());
    }

    public function testEntryContainsCorrectData(): void
    {
        $entry = new RouteEntry(
            resourceName: 'articles',
            controllerClass: 'App\\Http\\Controllers\\ArticleController',
            actions: ['index', 'show'],
            middleware: ['api', 'auth:sanctum'],
            prefix: 'api/v1',
        );

        $this->registry->register($entry);
        $stored = $this->registry->all()[0];

        $this->assertSame('articles', $stored->resourceName);
        $this->assertSame('App\\Http\\Controllers\\ArticleController', $stored->controllerClass);
        $this->assertSame(['index', 'show'], $stored->actions);
        $this->assertSame(['api', 'auth:sanctum'], $stored->middleware);
        $this->assertSame('api/v1', $stored->prefix);
    }
}
