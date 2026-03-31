<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Unit;

use Didasto\Apilot\Enums\AllowedFilter;
use PHPUnit\Framework\TestCase;

class AllowedFilterEnumTest extends TestCase
{
    public function testToOperatorReturnsCorrectSqlOperator(): void
    {
        $this->assertSame('=', AllowedFilter::EQUALS->toOperator());
        $this->assertSame('!=', AllowedFilter::NOT_EQUALS->toOperator());
        $this->assertSame('>', AllowedFilter::GT->toOperator());
        $this->assertSame('<', AllowedFilter::LT->toOperator());
        $this->assertSame('>=', AllowedFilter::GTE->toOperator());
        $this->assertSame('<=', AllowedFilter::LTE->toOperator());
        $this->assertSame('LIKE', AllowedFilter::LIKE->toOperator());
        $this->assertSame('NOT LIKE', AllowedFilter::NOT_LIKE->toOperator());
    }

    public function testIsSingleValueForComparisonOperators(): void
    {
        $this->assertTrue(AllowedFilter::EQUALS->isSingleValue());
        $this->assertTrue(AllowedFilter::NOT_EQUALS->isSingleValue());
        $this->assertTrue(AllowedFilter::GT->isSingleValue());
        $this->assertTrue(AllowedFilter::LT->isSingleValue());
        $this->assertTrue(AllowedFilter::GTE->isSingleValue());
        $this->assertTrue(AllowedFilter::LTE->isSingleValue());
    }

    public function testIsSingleValueForMultiValueOperators(): void
    {
        $this->assertFalse(AllowedFilter::IN->isSingleValue());
        $this->assertFalse(AllowedFilter::NOT_IN->isSingleValue());
        $this->assertFalse(AllowedFilter::BETWEEN->isSingleValue());
        $this->assertFalse(AllowedFilter::NOT_BETWEEN->isSingleValue());
    }

    public function testIsNoValueForNullOperators(): void
    {
        $this->assertTrue(AllowedFilter::IS_NULL->isNoValue());
        $this->assertTrue(AllowedFilter::IS_NOT_NULL->isNoValue());
    }

    public function testIsNoValueForOtherOperators(): void
    {
        $this->assertFalse(AllowedFilter::EQUALS->isNoValue());
        $this->assertFalse(AllowedFilter::IN->isNoValue());
        $this->assertFalse(AllowedFilter::LIKE->isNoValue());
    }

    public function testExactIsBackwardsCompatible(): void
    {
        $this->assertSame('=', AllowedFilter::EXACT->toOperator());
    }

    public function testPartialIsBackwardsCompatible(): void
    {
        $this->assertSame('LIKE', AllowedFilter::PARTIAL->toOperator());
    }

    public function testTryFromReturnsEnumForValidOperators(): void
    {
        $this->assertSame(AllowedFilter::EQUALS, AllowedFilter::tryFrom('eq'));
        $this->assertSame(AllowedFilter::NOT_EQUALS, AllowedFilter::tryFrom('neq'));
        $this->assertSame(AllowedFilter::IN, AllowedFilter::tryFrom('in'));
        $this->assertSame(AllowedFilter::NOT_IN, AllowedFilter::tryFrom('notIn'));
        $this->assertSame(AllowedFilter::GT, AllowedFilter::tryFrom('gt'));
        $this->assertSame(AllowedFilter::LT, AllowedFilter::tryFrom('lt'));
        $this->assertSame(AllowedFilter::GTE, AllowedFilter::tryFrom('gte'));
        $this->assertSame(AllowedFilter::LTE, AllowedFilter::tryFrom('lte'));
        $this->assertSame(AllowedFilter::LIKE, AllowedFilter::tryFrom('like'));
        $this->assertSame(AllowedFilter::NOT_LIKE, AllowedFilter::tryFrom('notLike'));
        $this->assertSame(AllowedFilter::BETWEEN, AllowedFilter::tryFrom('between'));
        $this->assertSame(AllowedFilter::NOT_BETWEEN, AllowedFilter::tryFrom('notBetween'));
        $this->assertSame(AllowedFilter::IS_NULL, AllowedFilter::tryFrom('isNull'));
        $this->assertSame(AllowedFilter::IS_NOT_NULL, AllowedFilter::tryFrom('isNotNull'));
    }

    public function testTryFromReturnsNullForInvalidOperators(): void
    {
        $this->assertNull(AllowedFilter::tryFrom('invalid'));
        $this->assertNull(AllowedFilter::tryFrom('equals'));
        $this->assertNull(AllowedFilter::tryFrom(''));
    }
}
