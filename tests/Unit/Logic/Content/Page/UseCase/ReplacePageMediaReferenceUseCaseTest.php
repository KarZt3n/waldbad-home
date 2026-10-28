<?php

namespace App\Tests\Unit\Logic\Content\Page\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;
use App\Logic\Content\Page\UseCase\ReplacePageMediaReferenceUseCase;
use PHPUnit\Framework\TestCase;

final class ReplacePageMediaReferenceUseCaseTest extends TestCase
{
    private const string URL = '/uploads/media/alt.png';

    public function testUpdatesRevisedDraftAndOlderPublicationSeparately(): void
    {
        $draft = $this->page('page', PageStatus::Draft, [new ContentBlock(type: ContentBlockType::RichText, content: '<p>Neu</p>')]);
        $publication = $this->page('page', PageStatus::Published, [
            new ContentBlock(type: ContentBlockType::Image, content: '', mediaUrl: self::URL),
            new ContentBlock(type: ContentBlockType::RichText, content: '<p>Alt</p>'),
        ]);
        $manager = $this->createMock(PageManagerInterface::class);
        $manager->method('all')->willReturn([$draft]);
        $manager->method('publishedPages')->willReturn([$publication]);
        $manager->expects(self::never())->method('saveAll');
        $manager->expects(self::once())->method('savePublishedBlocks')->with(self::callback(
            static fn (Page $page): bool => count($page->blocks) === 1 && $page->status === PageStatus::Published,
        ));

        self::assertSame(1, (new ReplacePageMediaReferenceUseCase($manager, $this->clock()))->execute(self::URL, null));
    }

    public function testSavesAffectedDraftsWithoutChangingTheirStatus(): void
    {
        $affected = $this->page('affected', PageStatus::Published, [new ContentBlock(type: ContentBlockType::Image, content: '', mediaUrl: self::URL)]);
        $unrelated = $this->page('unrelated', PageStatus::Draft, [new ContentBlock(type: ContentBlockType::RichText, content: '<p>Text</p>')]);
        $manager = $this->createMock(PageManagerInterface::class);
        $manager->method('all')->willReturn([$affected, $unrelated]);
        $manager->method('publishedPages')->willReturn([]);
        $manager->expects(self::once())->method('saveAll')->willReturnCallback(static function (array $pages): array {
            self::assertCount(1, $pages);
            self::assertInstanceOf(Page::class, $pages[0]);
            self::assertSame('/uploads/media/neu.png', $pages[0]->blocks[0]->mediaUrl);
            self::assertSame(PageStatus::Published, $pages[0]->status);

            return $pages;
        });

        self::assertSame(1, (new ReplacePageMediaReferenceUseCase($manager, $this->clock()))->execute(self::URL, '/uploads/media/neu.png'));
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-28T12:00:00+01:00'));

        return $clock;
    }

    /**
     * @param list<ContentBlock> $blocks
     */
    private function page(string $id, PageStatus $status, array $blocks): Page
    {
        $now = new \DateTimeImmutable('2026-10-01T10:00:00+02:00');

        return new Page(
            id: $id,
            title: 'Seite '.$id,
            slug: $id,
            navigationLabel: 'Seite',
            parentId: null,
            blocks: $blocks,
            status: $status,
            visible: true,
            showInNavigation: true,
            navigationPosition: 0,
            seoTitle: null,
            seoDescription: null,
            version: 2,
            createdAt: $now,
            updatedAt: $now,
            publishedAt: $now,
        );
    }
}
