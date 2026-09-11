<?php

namespace App\Tests\Unit\Logic\Event\HelpRequest\Manager;

use App\Logic\Event\HelpRequest\EventHelpRequestProcessorInterface;
use App\Logic\Event\HelpRequest\EventHelpRequestProviderInterface;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManager;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use App\Logic\Event\HelpRequest\Model\ParticipationInterval;
use PHPUnit\Framework\TestCase;

final class EventHelpRequestManagerTest extends TestCase
{
    public function testReturnsOnlyParticipatedRequestsOfTheGivenMembersInTheDateRange(): void
    {
        $inRangeMember1 = $this->request('member-1', EventHelpRequestStatus::Participated, '2026-06-01', 120);
        $inRangeMember2 = $this->request('member-2', EventHelpRequestStatus::Participated, '2026-12-31', 60);
        $beforeRange = $this->request('member-1', EventHelpRequestStatus::Participated, '2025-12-31', 90);
        // $to ist exklusiv: der Stichtag selbst zählt nicht mehr zum Zeitraum.
        $onToDate = $this->request('member-1', EventHelpRequestStatus::Participated, '2027-01-01', 90);
        $notParticipated = $this->request('member-1', EventHelpRequestStatus::NotParticipated, '2026-06-15', 0);
        $otherMember = $this->request('member-3', EventHelpRequestStatus::Participated, '2026-06-01', 300);
        $unlinked = $this->request(null, EventHelpRequestStatus::Participated, '2026-06-01', 45);

        $manager = $this->manager([
            $inRangeMember1, $inRangeMember2, $beforeRange, $onToDate, $notParticipated, $otherMember, $unlinked,
        ]);

        $result = $manager->findParticipatedForMembersInPeriod(
            ['member-1', 'member-2'],
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2027-01-01'),
        );

        self::assertSame([$inRangeMember1, $inRangeMember2], $result);
    }

    public function testReturnsAnEmptyListForNoMemberIds(): void
    {
        $manager = $this->manager([$this->request('member-1', EventHelpRequestStatus::Participated, '2026-06-01', 60)]);

        self::assertSame([], $manager->findParticipatedForMembersInPeriod([], new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2027-01-01')));
    }

    /**
     * @param list<EventHelpRequest> $requests
     */
    private function manager(array $requests): EventHelpRequestManager
    {
        $provider = $this->createStub(EventHelpRequestProviderInterface::class);
        $provider->method('findAll')->willReturn($requests);

        return new EventHelpRequestManager($provider, $this->createStub(EventHelpRequestProcessorInterface::class));
    }

    private static int $sequence = 0;

    private function request(?string $memberId, EventHelpRequestStatus $status, string $eventDate, int $participationMinutes): EventHelpRequest
    {
        $submittedAt = new \DateTimeImmutable('2026-01-01T10:00:00+01:00');
        $participated = $status === EventHelpRequestStatus::Participated;
        $toTime = sprintf('%02d:%02d', intdiv($participationMinutes, 60), $participationMinutes % 60);

        return new EventHelpRequest(
            id: 'request-'.(++self::$sequence),
            eventIdentifier: 'event-1',
            eventTitle: 'Frühjahrsputz',
            eventDate: $eventDate,
            eventTime: '10:00',
            firstName: 'Erika',
            lastName: 'Musterfrau',
            message: '',
            status: $status,
            participationMinutes: $participated ? $participationMinutes : 0,
            participationIntervals: $participated ? [new ParticipationInterval('interval-'.self::$sequence, 0, '00:00', $toTime)] : [],
            selectedActivities: [],
            submittedAt: $submittedAt,
            updatedAt: $submittedAt,
            memberId: $memberId,
        );
    }
}
