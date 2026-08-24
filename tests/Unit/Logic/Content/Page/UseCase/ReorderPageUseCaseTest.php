<?php

namespace App\Tests\Unit\Logic\Content\Page\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\Exception\ConcurrencyException;
use App\Logic\Content\Page\Dto\ReorderPageRequest;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;
use App\Logic\Content\Page\UseCase\ReorderPageUseCase;
use PHPUnit\Framework\TestCase;

final class ReorderPageUseCaseTest extends TestCase
{
    public function testMovesMainPageBelowAnotherPageAndNormalizesBothLevels(): void
    {
        $parent = $this->page('parent', 'Verein', null, 0);
        $moved = $this->page('moved', 'Vorstand', null, 1, version: 4);
        $existingChild = $this->page('child', 'Historie', 'parent', 0);
        $manager = $this->createMock(PageManagerInterface::class);
        $manager->expects(self::once())->method('get')->with('moved')->willReturn($moved);
        $manager->expects(self::once())->method('ensureParentAllowed')->with('parent', 'moved');
        $manager->expects(self::once())->method('all')->willReturn([$parent, $moved, $existingChild]);
        $manager->expects(self::once())->method('saveAll')->willReturnCallback(
            static function (array $pages): array {
                self::assertCount(3, $pages);
                $indexed = [];
                foreach ($pages as $page) {
                    self::assertInstanceOf(Page::class, $page);
                    $indexed[$page->id] = $page;
                }
                self::assertNull($indexed['parent']->parentId);
                self::assertSame(0, $indexed['parent']->navigationPosition);
                self::assertSame('parent', $indexed['moved']->parentId);
                self::assertSame(0, $indexed['moved']->navigationPosition);
                self::assertSame(PageStatus::Published, $indexed['moved']->status);
                self::assertSame('parent', $indexed['child']->parentId);
                self::assertSame(1, $indexed['child']->navigationPosition);

                return $pages;
            },
        );
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-19T12:00:00+02:00'));

        $response = (new ReorderPageUseCase($manager, $clock))->execute(new ReorderPageRequest(
            id: 'moved',
            parentId: 'parent',
            navigationPosition: 0,
            expectedVersion: 4,
        ));

        self::assertSame('parent', $response->parentId);
        self::assertSame(0, $response->navigationPosition);
        self::assertSame(PageStatus::Published, $response->status);
    }

    public function testRejectsStaleStructureChange(): void
    {
        $page = $this->page('page', 'Seite', null, 0, version: 3);
        $manager = $this->createMock(PageManagerInterface::class);
        $manager->expects(self::once())->method('get')->with('page')->willReturn($page);
        $manager->expects(self::never())->method('saveAll');
        $clock = $this->createStub(ClockInterface::class);

        $this->expectException(ConcurrencyException::class);
        (new ReorderPageUseCase($manager, $clock))->execute(new ReorderPageRequest(
            id: 'page',
            parentId: null,
            navigationPosition: 0,
            expectedVersion: 2,
        ));
    }

    public function testStartPageCannotBecomeSubpage(): void
    {
        $page = $this->page('start', 'Startseite', null, 0, slug: 'startseite');
        $manager = $this->createMock(PageManagerInterface::class);
        $manager->expects(self::once())->method('get')->with('start')->willReturn($page);
        $manager->expects(self::never())->method('ensureParentAllowed');
        $manager->expects(self::never())->method('saveAll');
        $clock = $this->createStub(ClockInterface::class);

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Hauptseite');
        (new ReorderPageUseCase($manager, $clock))->execute(new ReorderPageRequest(
            id: 'start',
            parentId: 'parent',
            navigationPosition: 0,
            expectedVersion: 1,
        ));
    }

    private function page(
        string $id,
        string $title,
        ?string $parentId,
        int $position,
        int $version = 1,
        ?string $slug = null,
    ): Page {
        $now = new \DateTimeImmutable('2026-08-19T10:00:00+02:00');

        return new Page(
            id: $id,
            title: $title,
            slug: $slug ?? $id,
            navigationLabel: $title,
            parentId: $parentId,
            blocks: [new ContentBlock(ContentBlockType::RichText, '<p>Inhalt</p>')],
            status: PageStatus::Published,
            visible: true,
            showInNavigation: true,
            navigationPosition: $position,
            seoTitle: $title,
            seoDescription: null,
            version: $version,
            createdAt: $now,
            updatedAt: $now,
            publishedAt: $now,
        );
    }
}
