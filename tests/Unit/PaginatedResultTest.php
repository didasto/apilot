<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Unit;

use Didasto\Apilot\Dto\PaginatedResult;
use PHPUnit\Framework\TestCase;

class PaginatedResultTest extends TestCase
{
    public function testLastPageCalculation(): void
    {
        $result = new PaginatedResult(
            items: [],
            total: 73,
            perPage: 15,
            currentPage: 1,
        );

        $this->assertSame(5, $result->lastPage());
    }

    public function testLastPageWithExactDivision(): void
    {
        $result = new PaginatedResult(
            items: [],
            total: 60,
            perPage: 15,
            currentPage: 1,
        );

        $this->assertSame(4, $result->lastPage());
    }

    public function testLastPageWithZeroTotal(): void
    {
        $result = new PaginatedResult(
            items: [],
            total: 0,
            perPage: 15,
            currentPage: 1,
        );

        // Should be at least 1, not 0
        $this->assertSame(1, $result->lastPage());
    }

    public function testLastPageWithOneItem(): void
    {
        $result = new PaginatedResult(
            items: [new \stdClass()],
            total: 1,
            perPage: 15,
            currentPage: 1,
        );

        $this->assertSame(1, $result->lastPage());
    }
}
