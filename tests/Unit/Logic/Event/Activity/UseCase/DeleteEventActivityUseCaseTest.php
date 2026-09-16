<?php

namespace App\Tests\Unit\Logic\Event\Activity\UseCase;

use App\Logic\Event\Activity\Manager\EventActivityManagerInterface;
use App\Logic\Event\Activity\Model\EventActivity;
use App\Logic\Event\Activity\UseCase\DeleteEventActivityUseCase;
use App\Logic\Event\Schedule\Manager\EventScheduleManagerInterface;
use App\Logic\Event\Template\Manager\EventTemplateManagerInterface;
use PHPUnit\Framework\TestCase;

final class DeleteEventActivityUseCaseTest extends TestCase
{
    /**
     * Kernstück: vor dem eigentlichen Löschen der Aktivität werden zunächst alle Referenzen aus
     * bestehenden Veranstaltungen/Arbeitseinsätzen und Vorlagen entfernt — sonst blieben verwaiste
     * `activityId`-Zeilen zurück (siehe `DeleteEventActivityUseCase`-Docblock).
     */
    public function testRemovesReferencesFromSchedulesAndTemplatesBeforeDeletingTheActivity(): void
    {
        $activity = $this->activity();

        $activities = $this->createMock(EventActivityManagerInterface::class);
        $activities->expects(self::once())->method('get')->with('activity-1')->willReturn($activity);
        $activities->expects(self::once())->method('delete')->with('activity-1');

        $schedules = $this->createMock(EventScheduleManagerInterface::class);
        $schedules->expects(self::once())->method('removeActivityReferences')->with('activity-1');

        $templates = $this->createMock(EventTemplateManagerInterface::class);
        $templates->expects(self::once())->method('removeActivityReferences')->with('activity-1');

        (new DeleteEventActivityUseCase($activities, $schedules, $templates))->execute('activity-1');
    }

    /**
     * Ist die Aktivität unbekannt, wirft bereits `$activities->get()` (siehe
     * `EventActivityManager::get()`) — es dürfen dann weder Referenzen entfernt noch die
     * (nicht existierende) Aktivität gelöscht werden.
     */
    public function testDoesNothingWhenTheActivityIsUnknown(): void
    {
        $activities = $this->createMock(EventActivityManagerInterface::class);
        $activities->method('get')->willThrowException(new \RuntimeException('not found'));
        $activities->expects(self::never())->method('delete');

        $schedules = $this->createMock(EventScheduleManagerInterface::class);
        $schedules->expects(self::never())->method('removeActivityReferences');

        $templates = $this->createMock(EventTemplateManagerInterface::class);
        $templates->expects(self::never())->method('removeActivityReferences');

        $this->expectException(\RuntimeException::class);

        (new DeleteEventActivityUseCase($activities, $schedules, $templates))->execute('unknown');
    }

    private function activity(): EventActivity
    {
        return new EventActivity(
            id: 'activity-1',
            name: 'Rasenmähen',
            description: '',
            active: true,
            defaultRequiredHelpers: 2,
            alwaysIncluded: false,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
