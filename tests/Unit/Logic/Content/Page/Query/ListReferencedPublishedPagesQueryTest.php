<?php

namespace App\Tests\Unit\Logic\Content\Page\Query;

use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\ActionButton;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\EventCallToAction;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Query\ListReferencedPublishedPagesQuery;
use App\Tests\Unit\Logic\Content\Footer\FooterTestPages;
use PHPUnit\Framework\TestCase;

final class ListReferencedPublishedPagesQueryTest extends TestCase
{
    public function testCollectsPagesNeededToShowThePage(): void
    {
        $query = $this->query([
            $this->page('p-start', [
                new ContentBlock(ContentBlockType::EmbeddedPage, '', embeddedPageId: 'p-embedded'),
                new ContentBlock(ContentBlockType::PageTeaser, '', layout: 'image_left', embeddedPageId: 'p-teaser'),
                new ContentBlock(ContentBlockType::RichText, '<p>Text</p>', actionButtons: [new ActionButton('Mehr', null, 'p-button')]),
                new ContentBlock(ContentBlockType::EventReference, '', embeddedPageId: 'p-events', eventIdentifier: 'fest'),
            ]),
            $this->page('p-embedded', [
                new ContentBlock(ContentBlockType::PageTeaser, '', layout: 'image_left', embeddedPageId: 'p-nested-teaser'),
                new ContentBlock(ContentBlockType::EmbeddedPage, '', embeddedPageId: 'p-start'),
            ]),
            $this->page('p-teaser', [new ContentBlock(ContentBlockType::EmbeddedPage, '', embeddedPageId: 'p-not-shown')]),
            $this->page('p-button'),
            $this->page('p-nested-teaser'),
            $this->page('p-events', [
                new ContentBlock(ContentBlockType::Event, '<p>Fest</p>', eventTitle: 'Fest', eventIdentifier: 'fest', eventCallToActions: [new EventCallToAction('Anmelden', null, 'p-signup')]),
                new ContentBlock(ContentBlockType::Event, '<p>Andere</p>', eventTitle: 'Andere', eventIdentifier: 'andere', eventCallToActions: [new EventCallToAction('Info', null, 'p-other-event')]),
            ]),
            $this->page('p-signup'),
            $this->page('p-other-event'),
            $this->page('p-not-shown'),
        ]);

        self::assertSame(
            ['p-embedded', 'p-teaser', 'p-button', 'p-events', 'p-nested-teaser', 'p-signup'],
            $this->ids($query->execute('p-start')),
            'Teaserziele werden nicht weiter aufgelöst, von Veranstaltungsseiten zählt nur die gezeigte Veranstaltung; die Seite selbst fehlt trotz Rückverweis.',
        );
    }

    public function testSkipsHiddenAndMissingPages(): void
    {
        $query = $this->query([
            $this->page('p-start', [
                new ContentBlock(ContentBlockType::EmbeddedPage, '', embeddedPageId: 'p-hidden'),
                new ContentBlock(ContentBlockType::PageTeaser, '', layout: 'image_left', embeddedPageId: 'p-missing'),
            ]),
            $this->page('p-hidden', visible: false),
        ]);

        self::assertSame([], $query->execute('p-start'));
        self::assertSame([], $query->execute('p-hidden'));
    }

    /** @param list<Page> $pages */
    private function query(array $pages): ListReferencedPublishedPagesQuery
    {
        $manager = $this->createStub(PageManagerInterface::class);
        $manager->method('publishedPages')->willReturn($pages);

        return new ListReferencedPublishedPagesQuery($manager);
    }

    /** @param list<ContentBlock> $blocks */
    private function page(string $id, array $blocks = [], bool $visible = true): Page
    {
        $page = FooterTestPages::page($id, $id, $id, visible: $visible);

        return $blocks === [] ? $page : new Page(
            id: $page->id,
            title: $page->title,
            slug: $page->slug,
            navigationLabel: $page->navigationLabel,
            parentId: null,
            blocks: $blocks,
            status: $page->status,
            visible: $page->visible,
            showInNavigation: false,
            navigationPosition: 0,
            seoTitle: null,
            seoDescription: null,
            version: 1,
            createdAt: $page->createdAt,
            updatedAt: $page->updatedAt,
            publishedAt: $page->publishedAt,
        );
    }

    /**
     * @param list<PageResponse> $pages
     *
     * @return list<string>
     */
    private function ids(array $pages): array
    {
        return array_map(static fn (PageResponse $page): string => $page->id, $pages);
    }
}
