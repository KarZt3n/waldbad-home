<?php

namespace App\Tests\Unit\Logic\Event\HelpRequest\Query;

use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use App\Logic\Event\HelpRequest\Model\VolunteerEvent;
use App\Logic\Event\HelpRequest\Query\ListEventHelpRequestsQuery;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;
use PHPUnit\Framework\TestCase;

final class ListEventHelpRequestsQueryTest extends TestCase
{
    public function testUsesCurrentEventDataAndFallsBackToTheStoredSnapshot(): void
    {
        $updatedRequest = $this->request('updated-event', 'Alter Titel', '2026-08-15', '14:00');
        $removedRequest = $this->request('removed-event', 'Historischer Titel', '2025-07-12', '10:00');
        $manager = $this->createStub(EventHelpRequestManagerInterface::class);
        $manager->method('all')->willReturn([$updatedRequest, $removedRequest]);
        $eventProvider = $this->createMock(VolunteerEventProviderInterface::class);
        $eventProvider->expects(self::exactly(2))
            ->method('findCurrent')
            ->willReturnMap([
                ['updated-event', new VolunteerEvent('updated-event', 'Neuer Titel', '2026-09-05', '16:30', [])],
                ['removed-event', null],
            ]);

        $responses = (new ListEventHelpRequestsQuery($manager, $eventProvider))->execute();

        self::assertSame('Neuer Titel', $responses[0]->eventTitle);
        self::assertSame('2026-09-05', $responses[0]->eventDate);
        self::assertSame('16:30', $responses[0]->eventTime);
        self::assertSame('Historischer Titel', $responses[1]->eventTitle);
        self::assertSame('2025-07-12', $responses[1]->eventDate);
        self::assertSame('10:00', $responses[1]->eventTime);
    }

    private function request(string $eventIdentifier, string $eventTitle, string $eventDate, string $eventTime): EventHelpRequest
    {
        $submittedAt = new \DateTimeImmutable('2026-08-01T12:00:00+02:00');

        return new EventHelpRequest(
            id: $eventIdentifier.'-request',
            eventIdentifier: $eventIdentifier,
            eventTitle: $eventTitle,
            eventDate: $eventDate,
            eventTime: $eventTime,
            firstName: 'Erika',
            lastName: 'Musterfrau',
            message: '',
            status: EventHelpRequestStatus::New,
            participationMinutes: null,
            participationIntervals: [],
            selectedActivities: [],
            submittedAt: $submittedAt,
            updatedAt: $submittedAt,
        );
    }
}
