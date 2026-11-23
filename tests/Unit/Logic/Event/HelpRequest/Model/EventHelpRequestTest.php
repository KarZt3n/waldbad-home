<?php

namespace App\Tests\Unit\Logic\Event\HelpRequest\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use App\Logic\Event\HelpRequest\Model\ParticipationInterval;
use PHPUnit\Framework\TestCase;

final class EventHelpRequestTest extends TestCase
{
    public function testCheckInOpensIntervalAndMarksAsPresent(): void
    {
        $present = $this->request()->checkIn('10:00', 'interval-1', $this->now());

        self::assertSame(EventHelpRequestStatus::Present, $present->status);
        self::assertSame(0, $present->participationMinutes);
        self::assertCount(1, $present->participationIntervals);
        self::assertSame('10:00', $present->participationIntervals[0]->fromTime);
        self::assertNull($present->participationIntervals[0]->toTime);
        self::assertSame($present->participationIntervals[0], $present->openParticipationInterval());
    }

    public function testCheckOutClosesOpenIntervalAndCountsMinutes(): void
    {
        $checkedOut = $this->request()
            ->checkIn('10:00', 'interval-1', $this->now())
            ->checkOut('13:15', 'interval-2', $this->now());

        self::assertSame(EventHelpRequestStatus::Participated, $checkedOut->status);
        self::assertSame(195, $checkedOut->participationMinutes);
        self::assertSame('interval-2', $checkedOut->participationIntervals[0]->id);
        self::assertSame('13:15', $checkedOut->participationIntervals[0]->toTime);
        self::assertNull($checkedOut->openParticipationInterval());
    }

    public function testCheckInAfterCheckOutAddsAnotherInterval(): void
    {
        $again = $this->request()
            ->checkIn('10:00', 'interval-1', $this->now())
            ->checkOut('12:00', 'interval-2', $this->now())
            ->checkIn('14:00', 'interval-3', $this->now());

        self::assertSame(EventHelpRequestStatus::Present, $again->status);
        self::assertSame(120, $again->participationMinutes);
        self::assertCount(2, $again->participationIntervals);
        self::assertSame('14:00', $again->participationIntervals[1]->fromTime);
        self::assertSame(1, $again->participationIntervals[1]->position);
    }

    public function testCheckInAgainKeepsCheckedOutIntervalUnchanged(): void
    {
        $again = $this->request()
            ->checkIn('09:15', 'interval-1', $this->now())
            ->checkOut('09:30', 'interval-2', $this->now())
            ->checkIn('09:45', 'interval-3', $this->now());

        self::assertCount(2, $again->participationIntervals);
        self::assertSame(['09:15', '09:30'], [$again->participationIntervals[0]->fromTime, $again->participationIntervals[0]->toTime]);
        self::assertSame(['09:45', null], [$again->participationIntervals[1]->fromTime, $again->participationIntervals[1]->toTime]);
        self::assertSame(15, $again->participationMinutes);
    }

    public function testCheckInWithinLastIntervalContinuesIt(): void
    {
        $continued = $this->request()
            ->checkIn('10:00', 'interval-1', $this->now())
            ->checkOut('12:15', 'interval-2', $this->now())
            ->checkIn('12:00', 'interval-3', $this->now());

        self::assertSame(EventHelpRequestStatus::Present, $continued->status);
        self::assertCount(1, $continued->participationIntervals);
        self::assertSame('interval-3', $continued->participationIntervals[0]->id);
        self::assertSame(['10:00', null], [$continued->participationIntervals[0]->fromTime, $continued->participationIntervals[0]->toTime]);
    }

    public function testCheckInAtEndOfLastIntervalStartsNewInterval(): void
    {
        $again = $this->request()
            ->checkIn('09:15', 'interval-1', $this->now())
            ->checkOut('09:30', 'interval-2', $this->now())
            ->checkIn('09:30', 'interval-3', $this->now());

        self::assertCount(2, $again->participationIntervals);
        self::assertSame(['09:15', '09:30'], [$again->participationIntervals[0]->fromTime, $again->participationIntervals[0]->toTime]);
        self::assertSame(['09:30', null], [$again->participationIntervals[1]->fromTime, $again->participationIntervals[1]->toTime]);
    }

    public function testCheckInTwiceIsRejected(): void
    {
        $present = $this->request()->checkIn('10:00', 'interval-1', $this->now());

        $this->expectException(BusinessRuleViolationException::class);
        $present->checkIn('11:00', 'interval-2', $this->now());
    }

    public function testCheckOutWithoutCheckInIsRejected(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->request()->checkOut('12:00', 'interval-1', $this->now());
    }

    public function testManualRecordingKeepsPersonPresentWhileLastIntervalIsOpen(): void
    {
        $recorded = $this->request()->recordParticipation(true, [
            new ParticipationInterval('interval-1', 0, '09:00', '10:30'),
            new ParticipationInterval('interval-2', 1, '11:00', null),
        ], $this->now());

        self::assertSame(EventHelpRequestStatus::Present, $recorded->status);
        self::assertSame(90, $recorded->participationMinutes);
    }

    public function testOpenIntervalMustBeTheLatest(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->request()->recordParticipation(true, [
            new ParticipationInterval('interval-1', 0, '09:00', null),
            new ParticipationInterval('interval-2', 1, '11:00', '12:00'),
        ], $this->now());
    }

    public function testOnlyOneIntervalMayBeOpen(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->request()->recordParticipation(true, [
            new ParticipationInterval('interval-1', 0, '09:00', null),
            new ParticipationInterval('interval-2', 1, '11:00', null),
        ], $this->now());
    }

    private function request(): EventHelpRequest
    {
        return new EventHelpRequest(
            id: 'request-1',
            eventIdentifier: 'event-1',
            eventTitle: 'Sommerfest',
            eventDate: '2026-07-04',
            eventTime: '10:00',
            firstName: 'Erika',
            lastName: 'Muster',
            message: '',
            status: EventHelpRequestStatus::New,
            participationMinutes: null,
            participationIntervals: [],
            selectedActivities: [],
            submittedAt: $this->now(),
            updatedAt: $this->now(),
        );
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-07-04 10:00');
    }
}
