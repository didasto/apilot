<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Unit;

use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\Filters\BooleanFilter;
use Didasto\Apilot\Filters\DateFilter;
use Didasto\Apilot\Filters\FilterSet;
use Didasto\Apilot\Filters\IdFilter;
use Didasto\Apilot\Filters\NumericFilter;
use Didasto\Apilot\Filters\TextFilter;
use Didasto\Apilot\Tests\Fixtures\Filters\CustomStatusFilter;
use PHPUnit\Framework\TestCase;

class FilterSetClassesTest extends TestCase
{
    public function testIdFilterContainsExpectedOperators(): void
    {
        $filters = IdFilter::make()->filters();

        $this->assertContains(AllowedFilter::EQUALS, $filters);
        $this->assertContains(AllowedFilter::NOT_EQUALS, $filters);
        $this->assertContains(AllowedFilter::IN, $filters);
        $this->assertContains(AllowedFilter::NOT_IN, $filters);
    }

    public function testNumericFilterContainsExpectedOperators(): void
    {
        $filters = NumericFilter::make()->filters();

        $this->assertContains(AllowedFilter::EQUALS, $filters);
        $this->assertContains(AllowedFilter::NOT_EQUALS, $filters);
        $this->assertContains(AllowedFilter::IN, $filters);
        $this->assertContains(AllowedFilter::NOT_IN, $filters);
        $this->assertContains(AllowedFilter::GT, $filters);
        $this->assertContains(AllowedFilter::LT, $filters);
        $this->assertContains(AllowedFilter::GTE, $filters);
        $this->assertContains(AllowedFilter::LTE, $filters);
        $this->assertContains(AllowedFilter::BETWEEN, $filters);
    }

    public function testTextFilterContainsExpectedOperators(): void
    {
        $filters = TextFilter::make()->filters();

        $this->assertContains(AllowedFilter::EQUALS, $filters);
        $this->assertContains(AllowedFilter::NOT_EQUALS, $filters);
        $this->assertContains(AllowedFilter::LIKE, $filters);
        $this->assertContains(AllowedFilter::NOT_LIKE, $filters);
        $this->assertContains(AllowedFilter::IN, $filters);
        $this->assertContains(AllowedFilter::IS_NULL, $filters);
        $this->assertContains(AllowedFilter::IS_NOT_NULL, $filters);
    }

    public function testDateFilterContainsExpectedOperators(): void
    {
        $filters = DateFilter::make()->filters();

        $this->assertContains(AllowedFilter::EQUALS, $filters);
        $this->assertContains(AllowedFilter::NOT_EQUALS, $filters);
        $this->assertContains(AllowedFilter::GT, $filters);
        $this->assertContains(AllowedFilter::LT, $filters);
        $this->assertContains(AllowedFilter::GTE, $filters);
        $this->assertContains(AllowedFilter::LTE, $filters);
        $this->assertContains(AllowedFilter::BETWEEN, $filters);
        $this->assertContains(AllowedFilter::IS_NULL, $filters);
        $this->assertContains(AllowedFilter::IS_NOT_NULL, $filters);
    }

    public function testBooleanFilterContainsExpectedOperators(): void
    {
        $filters = BooleanFilter::make()->filters();

        $this->assertContains(AllowedFilter::EQUALS, $filters);
        $this->assertContains(AllowedFilter::IS_NULL, $filters);
        $this->assertContains(AllowedFilter::IS_NOT_NULL, $filters);
    }

    public function testCustomFilterSetCanBeCreated(): void
    {
        $filters = CustomStatusFilter::make()->filters();

        $this->assertContains(AllowedFilter::EQUALS, $filters);
        $this->assertContains(AllowedFilter::NOT_EQUALS, $filters);
        $this->assertContains(AllowedFilter::IN, $filters);
        $this->assertCount(3, $filters);
    }

    public function testFilterSetMakeReturnsInstance(): void
    {
        $this->assertInstanceOf(IdFilter::class, IdFilter::make());
        $this->assertInstanceOf(FilterSet::class, IdFilter::make());
    }

    public function testFilterSetIsNotEmpty(): void
    {
        $this->assertNotEmpty(IdFilter::make()->filters());
        $this->assertNotEmpty(NumericFilter::make()->filters());
        $this->assertNotEmpty(TextFilter::make()->filters());
        $this->assertNotEmpty(DateFilter::make()->filters());
        $this->assertNotEmpty(BooleanFilter::make()->filters());
    }
}
