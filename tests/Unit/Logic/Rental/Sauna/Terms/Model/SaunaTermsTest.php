<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Terms\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Terms\Model\SaunaTerms;
use PHPUnit\Framework\TestCase;

final class SaunaTermsTest extends TestCase
{
    public function testDefaultsAreTwentyEuroForTwoHoursForGroupsOfTwoToSix(): void
    {
        $terms = SaunaTerms::defaults();

        self::assertSame(2000, $terms->priceCents);
        self::assertSame(120, $terms->priceUnitMinutes);
        self::assertSame(2, $terms->minPersons);
        self::assertSame(6, $terms->maxPersons);
    }

    public function testPriceIsProratedByBookedDuration(): void
    {
        $terms = SaunaTerms::defaults();

        self::assertSame(2000, $terms->priceFor(120));
        self::assertSame(1000, $terms->priceFor(60));
        self::assertSame(3000, $terms->priceFor(180));
        self::assertSame(1500, $terms->priceFor(90));
    }

    public function testGroupSizeMustBeWithinLimits(): void
    {
        $terms = SaunaTerms::defaults();
        $terms->assertGroupSize(2);
        $terms->assertGroupSize(6);

        $this->expectException(BusinessRuleViolationException::class);
        $terms->assertGroupSize(7);
    }

    public function testSinglePersonIsNeverAllowed(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Einzelnutzung');

        new SaunaTerms(priceCents: 2000, priceUnitMinutes: 120, minPersons: 1, maxPersons: 6, updatedAt: null);
    }

    public function testMaximumMustNotBeBelowMinimum(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new SaunaTerms(priceCents: 2000, priceUnitMinutes: 120, minPersons: 4, maxPersons: 3, updatedAt: null);
    }
}
