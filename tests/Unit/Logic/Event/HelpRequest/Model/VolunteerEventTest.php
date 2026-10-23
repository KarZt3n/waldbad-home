<?php

namespace App\Tests\Unit\Logic\Event\HelpRequest\Model;

use App\Logic\Event\HelpRequest\Model\VolunteerEvent;
use PHPUnit\Framework\TestCase;

final class VolunteerEventTest extends TestCase
{
    public function testHelpRegistrationClosesExactly24HoursAfterTheStart(): void
    {
        $event = new VolunteerEvent('event-1', 'Frühjahrsputz', '2026-10-24', '09:00', []);

        self::assertFalse($event->isHelpRegistrationClosed(new \DateTimeImmutable('2026-10-25 08:59')));
        self::assertTrue($event->isHelpRegistrationClosed(new \DateTimeImmutable('2026-10-25 09:00')));
    }

    public function testEventWithoutTimeCountsFromMidnight(): void
    {
        $event = new VolunteerEvent('event-1', 'Frühjahrsputz', '2026-10-24', '', []);

        self::assertFalse($event->isHelpRegistrationClosed(new \DateTimeImmutable('2026-10-24 23:59')));
        self::assertTrue($event->isHelpRegistrationClosed(new \DateTimeImmutable('2026-10-25 00:00')));
    }

    public function testUnreadableDateKeepsTheRegistrationOpen(): void
    {
        $event = new VolunteerEvent('event-1', 'Frühjahrsputz', 'demnächst', '09:00', []);

        self::assertFalse($event->isHelpRegistrationClosed(new \DateTimeImmutable('2030-01-01')));
    }
}
