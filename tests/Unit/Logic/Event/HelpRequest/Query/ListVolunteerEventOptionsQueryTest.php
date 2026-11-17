<?php

namespace App\Tests\Unit\Logic\Event\HelpRequest\Query;

use App\Logic\Event\HelpRequest\Dto\VolunteerEventOptionResponse;
use App\Logic\Event\HelpRequest\Model\VolunteerEvent;
use App\Logic\Event\HelpRequest\Query\ListVolunteerEventOptionsQuery;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;
use PHPUnit\Framework\TestCase;

final class ListVolunteerEventOptionsQueryTest extends TestCase
{
    public function testSortsEventsByDateAndTime(): void
    {
        $provider = $this->createStub(VolunteerEventProviderInterface::class);
        $provider->method('findAllWithHelpEnabled')->willReturn([
            new VolunteerEvent('c', 'Herbstputz', '2026-10-03', '09:00', []),
            new VolunteerEvent('b', 'Nachmittag', '2026-04-11', '14:00', []),
            new VolunteerEvent('a', 'Frühjahrsputz', '2026-04-11', '09:00', []),
        ]);

        $options = (new ListVolunteerEventOptionsQuery($provider))->execute();

        self::assertSame(['a', 'b', 'c'], array_map(static fn (VolunteerEventOptionResponse $option): string => $option->eventIdentifier, $options));
        self::assertSame('Frühjahrsputz', $options[0]->eventTitle);
    }
}
