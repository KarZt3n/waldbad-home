<?php

namespace App\Tests\Unit\Logic\Content\Footer\Query;

use App\Logic\Content\Footer\Dto\FooterLinkResponse;
use App\Logic\Content\Footer\Manager\FooterNavigationManagerInterface;
use App\Logic\Content\Footer\Model\FooterNavigation;
use App\Logic\Content\Footer\Query\GetPublicFooterLinksQuery;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Tests\Unit\Logic\Content\Footer\FooterTestPages;
use PHPUnit\Framework\TestCase;

final class GetPublicFooterLinksQueryTest extends TestCase
{
    public function testListsVisiblePublishedPagesInFooterOrder(): void
    {
        $links = $this->query(new FooterNavigation(['p-hidden', 'p-supporters', 'p-imprint']))->execute();

        self::assertSame(
            [['Unterstützer', 'unterstuetzer'], ['Impressum', 'impressum']],
            array_map(static fn (FooterLinkResponse $link): array => [$link->label, $link->slug], $links),
        );
    }

    public function testUsesFormerFooterUntilSavedOnce(): void
    {
        $links = $this->query(null)->execute();

        self::assertSame(['impressum', 'unterstuetzer'], array_map(static fn (FooterLinkResponse $link): string => $link->slug, $links));
    }

    private function query(?FooterNavigation $navigation): GetPublicFooterLinksQuery
    {
        $manager = $this->createStub(FooterNavigationManagerInterface::class);
        $manager->method('get')->willReturn($navigation);
        $pageManager = $this->createStub(PageManagerInterface::class);
        $pageManager->method('publishedPages')->willReturn([
            FooterTestPages::page('p-imprint', 'impressum', 'Impressum'),
            FooterTestPages::page('p-supporters', 'unterstuetzer', 'Unterstützer'),
            FooterTestPages::page('p-hidden', 'kontakt', 'Kontakt', visible: false),
        ]);

        return new GetPublicFooterLinksQuery($manager, $pageManager);
    }
}
