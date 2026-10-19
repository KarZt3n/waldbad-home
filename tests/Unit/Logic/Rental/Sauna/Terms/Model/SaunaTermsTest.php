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
        self::assertSame(2, $terms->leadDays);
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

        new SaunaTerms(priceCents: 2000, priceUnitMinutes: 120, minPersons: 1, maxPersons: 6, updatedAt: null, leadDays: 2);
    }

    public function testMaximumMustNotBeBelowMinimum(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new SaunaTerms(priceCents: 2000, priceUnitMinutes: 120, minPersons: 4, maxPersons: 3, updatedAt: null, leadDays: 2);
    }

    public function testPriceUnitIsTheFixedDurationOfEveryBooking(): void
    {
        $terms = SaunaTerms::defaults();
        $terms->assertBookingDuration(120);

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Eine Sauna-Buchung dauert immer genau 2 Stunden.');
        $terms->assertBookingDuration(105);
    }

    public function testLongerBookingsThanThePriceUnitAreRejected(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Eine Sauna-Buchung dauert immer genau 2 Stunden.');
        SaunaTerms::defaults()->assertBookingDuration(180);
    }

    public function testBookingDurationMessageUsesMinutesForUnevenDurations(): void
    {
        $this->expectExceptionMessage('Eine Sauna-Buchung dauert immer genau 90 Minuten.');
        (new SaunaTerms(2000, 90, 2, 6, null, 2))->assertBookingDuration(60);
    }

    public function testEarliestBookableDayIsTodayPlusLeadDays(): void
    {
        $terms = new SaunaTerms(2000, 120, 2, 6, null, 2);

        self::assertSame('2026-10-21', $terms->earliestBookableDay(new \DateTimeImmutable('2026-10-19T23:30:00'))->format('Y-m-d'));
        $terms->assertLeadTime(new \DateTimeImmutable('2026-10-21'), new \DateTimeImmutable('2026-10-19T23:30:00'));

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Die Sauna kann nur mit 2 Tagen Vorlauf angefragt werden, frühestens für den 21.10.2026.');
        $terms->assertLeadTime(new \DateTimeImmutable('2026-10-20'), new \DateTimeImmutable('2026-10-19T08:00:00'));
    }

    public function testLeadDaysOfZeroAllowSameDayRequests(): void
    {
        (new SaunaTerms(2000, 120, 2, 6, null, 0))->assertLeadTime(new \DateTimeImmutable('2026-10-19'), new \DateTimeImmutable('2026-10-19T08:00:00'));

        $this->addToAssertionCount(1);
    }

    public function testLeadDaysMustBeWithinLimits(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Der Vorlauf muss zwischen 0 und 60 Tagen liegen.');

        new SaunaTerms(2000, 120, 2, 6, null, 61);
    }
}
