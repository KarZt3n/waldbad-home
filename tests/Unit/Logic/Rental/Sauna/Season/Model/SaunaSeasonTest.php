<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Season\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Season\Model\SaunaClosure;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\Model\SaunaTimeSlot;
use App\Logic\Rental\Sauna\Season\Model\Weekday;
use PHPUnit\Framework\TestCase;

final class SaunaSeasonTest extends TestCase
{
    public function testSplitsWeekdayWindowsIntoSlotsOfConfiguredDuration(): void
    {
        $season = $this->season(openingHours: [
            new SaunaOpeningHours(Weekday::Monday, '18:00', '20:30'),
            new SaunaOpeningHours(Weekday::Monday, '10:00', '12:00'),
        ]);

        // 2026-10-05 ist ein Montag; das angebrochene Intervall 20:00–20:30 ergibt keine Einheit.
        $slots = array_map(
            static fn (SaunaTimeSlot $slot): string => $slot->startTime.'-'.$slot->endTime,
            $season->slotsOn(new \DateTimeImmutable('2026-10-05')),
        );

        self::assertSame(['10:00-11:00', '11:00-12:00', '18:00-19:00', '19:00-20:00'], $slots);
        self::assertSame([], $season->slotsOn(new \DateTimeImmutable('2026-10-06')));
    }

    public function testDaysOutsideTheSeasonHaveNoSlots(): void
    {
        $season = $this->season(startsOn: '2026-10-01', endsOn: '2027-03-31');

        self::assertSame([], $season->slotsOn(new \DateTimeImmutable('2026-09-28')));
        self::assertSame([], $season->slotsOn(new \DateTimeImmutable('2027-04-05')));
        self::assertTrue($season->covers(new \DateTimeImmutable('2027-03-31')));
    }

    public function testSeasonWithoutEndCoversAllFollowingDays(): void
    {
        $season = $this->season(startsOn: '2026-10-01', endsOn: null);

        self::assertTrue($season->covers(new \DateTimeImmutable('2030-01-01')));
        self::assertFalse($season->covers(new \DateTimeImmutable('2026-09-30')));
    }

    public function testClosedSeasonIsNoLongerBookableFromClosingDayWithoutChangingEndDate(): void
    {
        $season = $this->season(startsOn: '2026-10-01', endsOn: null)
            ->close(new \DateTimeImmutable('2026-12-01T15:30:00'), new \DateTimeImmutable('2026-11-20T10:00:00'));

        self::assertTrue($season->isClosed());
        self::assertNull($season->endsOn);
        self::assertTrue($season->covers(new \DateTimeImmutable('2026-11-30')));
        self::assertFalse($season->covers(new \DateTimeImmutable('2026-12-01')));
        self::assertSame([], $season->slotsOn(new \DateTimeImmutable('2026-12-07')));
    }

    public function testUpcomingOnlyWhileStartIsAheadAndNotClosedBeforehand(): void
    {
        $today = new \DateTimeImmutable('2026-09-25');
        $upcoming = $this->season(startsOn: '2026-10-01', endsOn: null);

        self::assertTrue($upcoming->isUpcoming($today));
        self::assertFalse($upcoming->isUpcoming(new \DateTimeImmutable('2026-10-01')));
        self::assertFalse($upcoming->close($today, $today)->isUpcoming($today));
    }

    public function testClosingTwiceIsRejected(): void
    {
        $now = new \DateTimeImmutable('2026-11-20T10:00:00');
        $closed = $this->season()->close($now, $now);

        $this->expectException(BusinessRuleViolationException::class);
        $closed->close($now, $now);
    }

    public function testReopenedSeasonIsBookableAgain(): void
    {
        $now = new \DateTimeImmutable('2026-11-20T10:00:00');
        $reopened = $this->season(startsOn: '2026-10-01', endsOn: null)->close($now, $now)->reopen($now);

        self::assertFalse($reopened->isClosed());
        self::assertTrue($reopened->covers(new \DateTimeImmutable('2026-12-01')));
    }

    public function testReopeningAnOpenSeasonIsRejected(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->season()->reopen(new \DateTimeImmutable('2026-11-20T10:00:00'));
    }

    public function testBookableRangeMustBeContiguousSlotsWithinOneWindow(): void
    {
        $season = $this->season(openingHours: [
            new SaunaOpeningHours(Weekday::Monday, '10:00', '12:00'),
            new SaunaOpeningHours(Weekday::Monday, '14:00', '16:00'),
        ]);
        $monday = new \DateTimeImmutable('2026-10-05');

        self::assertTrue($season->isBookableRange($monday, '10:00', '12:00'));
        self::assertTrue($season->isBookableRange($monday, '15:00', '16:00'));
        self::assertFalse($season->isBookableRange($monday, '11:00', '15:00'));
        self::assertFalse($season->isBookableRange($monday, '10:30', '11:30'));
        self::assertFalse($season->isBookableRange(new \DateTimeImmutable('2026-10-06'), '10:00', '11:00'));
    }

    public function testRejectsEndBeforeStart(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        $this->season(startsOn: '2026-10-01', endsOn: '2026-09-01');
    }

    public function testRejectsOverlappingWindowsOnTheSameWeekday(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        $this->season(openingHours: [
            new SaunaOpeningHours(Weekday::Friday, '16:00', '19:00'),
            new SaunaOpeningHours(Weekday::Friday, '18:00', '21:00'),
        ]);
    }

    public function testRejectsWindowShorterThanOneSlot(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        $this->season(openingHours: [new SaunaOpeningHours(Weekday::Friday, '16:00', '16:30')]);
    }

    public function testRejectsSeasonWithoutOpeningHours(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        $this->season(openingHours: []);
    }

    public function testClosureDaysHaveNoSlotsButOtherDaysKeepTheirPlan(): void
    {
        // 2026-10-05 und 2026-10-12 sind Montage; nur der erste liegt in der Schließzeit.
        $season = $this->season(closures: [new SaunaClosure(new \DateTimeImmutable('2026-10-05'), new \DateTimeImmutable('2026-10-07'), 'Revision')]);

        self::assertSame([], $season->slotsOn(new \DateTimeImmutable('2026-10-05')));
        self::assertSame('Revision', $season->closureOn(new \DateTimeImmutable('2026-10-07'))?->reason);
        self::assertNull($season->closureOn(new \DateTimeImmutable('2026-10-08')));
        self::assertCount(3, $season->slotsOn(new \DateTimeImmutable('2026-10-12')));
        self::assertSame(
            'Die Sauna ist am 06.10.2026 geschlossen (Revision).',
            $season->closureOn(new \DateTimeImmutable('2026-10-06'))?->messageFor(new \DateTimeImmutable('2026-10-06')),
        );
    }

    public function testClosureMustLieWithinTheSeason(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('innerhalb des Saisonzeitraums');

        $this->season(closures: [new SaunaClosure(new \DateTimeImmutable('2027-03-30'), new \DateTimeImmutable('2027-04-02'))]);
    }

    public function testClosuresMustNotOverlap(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('nicht überschneiden');

        $this->season(closures: [
            new SaunaClosure(new \DateTimeImmutable('2026-12-24'), new \DateTimeImmutable('2026-12-26')),
            new SaunaClosure(new \DateTimeImmutable('2026-12-26'), new \DateTimeImmutable('2026-12-26')),
        ]);
    }

    public function testClosureEndMustNotPrecedeItsStart(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new SaunaClosure(new \DateTimeImmutable('2026-12-26'), new \DateTimeImmutable('2026-12-24'));
    }

    public function testLastBookableDayIsTheEarlierOfEndAndDayBeforeClosing(): void
    {
        $now = new \DateTimeImmutable('2026-10-01T10:00:00');

        self::assertSame('2027-03-31', $this->season()->lastBookableDay()?->format('Y-m-d'));
        self::assertNull($this->season(endsOn: null)->lastBookableDay());
        self::assertSame('2026-11-14', $this->season()->close(new \DateTimeImmutable('2026-11-15'), $now)->lastBookableDay()?->format('Y-m-d'));
        self::assertSame('2026-11-14', $this->season(endsOn: null)->close(new \DateTimeImmutable('2026-11-15'), $now)->lastBookableDay()?->format('Y-m-d'));
        self::assertSame('2027-03-31', $this->season()->close(new \DateTimeImmutable('2027-06-01'), $now)->lastBookableDay()?->format('Y-m-d'));
    }

    public function testClosingAndReopeningKeepsTheClosures(): void
    {
        $closures = [new SaunaClosure(new \DateTimeImmutable('2026-12-24'), new \DateTimeImmutable('2026-12-26'), 'Weihnachten')];
        $now = new \DateTimeImmutable('2026-10-01T10:00:00');

        $season = $this->season(closures: $closures)->close($now, $now)->reopen($now);

        self::assertEquals($closures, $season->closures);
    }

    /**
     * @param list<SaunaOpeningHours>|null $openingHours
     * @param list<SaunaClosure>           $closures
     */
    private function season(string $startsOn = '2026-10-01', ?string $endsOn = '2027-03-31', ?array $openingHours = null, array $closures = []): SaunaSeason
    {
        $now = new \DateTimeImmutable('2026-09-25T10:00:00');

        return new SaunaSeason(
            id: 'season-1',
            startsOn: new \DateTimeImmutable($startsOn),
            endsOn: $endsOn === null ? null : new \DateTimeImmutable($endsOn),
            slotDurationMinutes: 60,
            openingHours: $openingHours ?? [new SaunaOpeningHours(Weekday::Monday, '18:00', '21:00')],
            createdAt: $now,
            updatedAt: $now,
            closures: $closures,
        );
    }
}
