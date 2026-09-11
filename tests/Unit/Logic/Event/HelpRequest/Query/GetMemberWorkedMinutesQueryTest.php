<?php

namespace App\Tests\Unit\Logic\Event\HelpRequest\Query;

use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use App\Logic\Event\HelpRequest\Model\ParticipationInterval;
use App\Logic\Event\HelpRequest\Query\GetMemberWorkedMinutesQuery;
use PHPUnit\Framework\TestCase;

final class GetMemberWorkedMinutesQueryTest extends TestCase
{
    /**
     * Stunden sind innerhalb der Familie übertragbar: ein Mitglied ohne eigene Anmeldung taucht mit
     * 0 Minuten auf, ein anderes trägt entsprechend mehr bei — `totalMinutes()` summiert über alle.
     */
    public function testSumsMinutesPerMemberAndInTotal(): void
    {
        $from = new \DateTimeImmutable('2026-01-01');
        $to = new \DateTimeImmutable('2027-01-01');

        $manager = $this->createMock(EventHelpRequestManagerInterface::class);
        // minutesByMember() und totalMinutes() rufen den Manager je einmal auf (totalMinutes() nutzt
        // minutesByMember() intern), daher zweimal statt einmal.
        $manager->expects(self::exactly(2))->method('findParticipatedForMembersInPeriod')
            ->with(['parent-1', 'parent-2', 'child-1'], $from, $to)
            ->willReturn([
                $this->request('parent-1', 180),
                $this->request('parent-1', 60),
                $this->request('parent-2', 120),
            ]);

        $query = new GetMemberWorkedMinutesQuery($manager);

        $byMember = $query->minutesByMember(['parent-1', 'parent-2', 'child-1'], $from, $to);
        self::assertSame(['parent-1' => 240, 'parent-2' => 120, 'child-1' => 0], $byMember);
        self::assertSame(360, $query->totalMinutes(['parent-1', 'parent-2', 'child-1'], $from, $to));
    }

    private function request(string $memberId, int $minutes): EventHelpRequest
    {
        $submittedAt = new \DateTimeImmutable('2026-06-01T10:00:00+02:00');
        $toTime = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);

        return new EventHelpRequest(
            id: 'request-'.$memberId.'-'.$minutes,
            eventIdentifier: 'event-1',
            eventTitle: 'Frühjahrsputz',
            eventDate: '2026-06-01',
            eventTime: '10:00',
            firstName: 'Erika',
            lastName: 'Musterfrau',
            message: '',
            status: EventHelpRequestStatus::Participated,
            participationMinutes: $minutes,
            participationIntervals: [new ParticipationInterval('interval-'.$memberId.'-'.$minutes, 0, '00:00', $toTime)],
            selectedActivities: [],
            submittedAt: $submittedAt,
            updatedAt: $submittedAt,
            memberId: $memberId,
        );
    }
}
