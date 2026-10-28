<?php

namespace App\Tests\Unit\Logic\Overview\Query;

use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\Overview\Dto\GetOverviewRequest;
use App\Logic\Overview\Model\OverviewTaskType;
use App\Logic\Overview\OverviewTaskSourceInterface;
use App\Logic\Overview\Query\GetOverviewQuery;
use PHPUnit\Framework\TestCase;

final class GetOverviewQueryTest extends TestCase
{
    public function testCountsOpenItemsAndReportsTheEarliestInDisplayOrder(): void
    {
        $query = new GetOverviewQuery([
            $this->source(OverviewTaskType::GuestbookEntries, []),
            $this->source(OverviewTaskType::ContactRequests, ['2026-10-20', '2026-09-01', '2026-10-27']),
            $this->source(OverviewTaskType::MembershipApplications, ['2026-10-01']),
        ]);

        $tasks = $query->execute(new GetOverviewRequest([CmsModule::Guestbook, CmsModule::ContactRequests, CmsModule::MembershipApplications]));

        self::assertSame(
            [OverviewTaskType::MembershipApplications, OverviewTaskType::ContactRequests, OverviewTaskType::GuestbookEntries],
            array_map(static fn ($task): OverviewTaskType => $task->type, $tasks),
        );
        self::assertSame([1, 3, 0], array_map(static fn ($task): int => $task->count, $tasks));
        self::assertSame('2026-09-01', $tasks[1]->earliestAt?->format('Y-m-d'));
        self::assertNull($tasks[2]->earliestAt);
    }

    public function testSkipsModulesWithoutAccessWithoutQueryingThem(): void
    {
        $hidden = $this->createMock(OverviewTaskSourceInterface::class);
        $hidden->method('type')->willReturn(OverviewTaskType::MemberMessages);
        $hidden->expects(self::never())->method('openItemDates');

        $tasks = (new GetOverviewQuery([$hidden, $this->source(OverviewTaskType::PagesInReview, ['2026-10-28'])]))
            ->execute(new GetOverviewRequest([CmsModule::Pages]));

        self::assertCount(1, $tasks);
        self::assertSame(OverviewTaskType::PagesInReview, $tasks[0]->type);
    }

    public function testSaunaDutiesBelongToTheSaunaModule(): void
    {
        $tasks = (new GetOverviewQuery([$this->source(OverviewTaskType::SaunaDuties, ['2026-11-02 17:00'])]))
            ->execute(new GetOverviewRequest([CmsModule::RentalSauna]));

        self::assertSame(OverviewTaskType::SaunaDuties, $tasks[0]->type);
        self::assertSame('2026-11-02 17:00', $tasks[0]->earliestAt?->format('Y-m-d H:i'));
    }

    /**
     * @param list<string> $dates
     */
    private function source(OverviewTaskType $type, array $dates): OverviewTaskSourceInterface
    {
        $source = $this->createStub(OverviewTaskSourceInterface::class);
        $source->method('type')->willReturn($type);
        $source->method('openItemDates')->willReturn(array_map(
            static fn (string $date): \DateTimeImmutable => new \DateTimeImmutable($date),
            $dates,
        ));

        return $source;
    }
}
