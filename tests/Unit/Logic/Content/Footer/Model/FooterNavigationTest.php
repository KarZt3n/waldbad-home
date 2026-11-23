<?php

namespace App\Tests\Unit\Logic\Content\Footer\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\Footer\Model\FooterNavigation;
use App\Logic\Content\Page\Model\Page;
use App\Tests\Unit\Logic\Content\Footer\FooterTestPages;
use PHPUnit\Framework\TestCase;

final class FooterNavigationTest extends TestCase
{
    public function testDefaultsMatchFormerFixedFooterOrder(): void
    {
        $pages = [
            FooterTestPages::page('p-supporters', 'unterstuetzer', 'Unterstützer'),
            FooterTestPages::page('p-imprint', 'impressum', 'Impressum'),
            FooterTestPages::page('p-other', 'anfahrt', 'Anfahrt'),
            FooterTestPages::page('p-contact', 'kontakt', 'Kontakt'),
        ];

        self::assertSame(['p-imprint', 'p-contact', 'p-supporters'], FooterNavigation::defaults($pages)->pageIds);
    }

    public function testPagesInKeepsFooterOrderAndSkipsMissingPages(): void
    {
        $navigation = new FooterNavigation(['p-b', 'p-deleted', 'p-a']);
        $pages = [FooterTestPages::page('p-a', 'a', 'A'), FooterTestPages::page('p-b', 'b', 'B')];

        self::assertSame(['p-b', 'p-a'], array_map(static fn (Page $page): string => $page->id, $navigation->pagesIn($pages)));
    }

    public function testRejectsDuplicatePages(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        new FooterNavigation(['p-a', 'p-a']);
    }

    public function testRejectsTooManyPages(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        new FooterNavigation(array_map(static fn (int $index): string => 'p-'.$index, range(1, FooterNavigation::MAX_PAGES + 1)));
    }
}
