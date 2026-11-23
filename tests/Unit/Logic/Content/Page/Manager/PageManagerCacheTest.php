<?php

namespace App\Tests\Unit\Logic\Content\Page\Manager;

use App\Logic\Content\Page\Exception\PageNotFoundException;
use App\Logic\Content\Page\Manager\PageManager;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\PageProcessorInterface;
use App\Logic\Content\Page\PageProviderInterface;
use App\Tests\Support\InMemoryModelCache;
use App\Tests\Unit\Logic\Content\Footer\FooterTestPages;
use PHPUnit\Framework\TestCase;

final class PageManagerCacheTest extends TestCase
{
    public function testPublicReadsShareOneDatabaseQueryUntilAPageChanges(): void
    {
        $provider = $this->createMock(PageProviderInterface::class);
        $provider->expects(self::exactly(2))->method('findAllPublished')->willReturn([
            FooterTestPages::page('p-home', 'startseite', 'Start'),
            FooterTestPages::page('p-hidden', 'intern', 'Intern', visible: false),
        ]);
        $processor = $this->createStub(PageProcessorInterface::class);
        $processor->method('save')->willReturnArgument(0);
        $manager = new PageManager($provider, $processor, new InMemoryModelCache());

        self::assertSame('p-home', $manager->getPublished('startseite')->id);
        self::assertSame('p-home', $manager->getPublishedById('p-home')->id);
        self::assertSame(['p-home'], array_map(static fn (Page $page): string => $page->id, $manager->navigation()));
        self::assertCount(2, $manager->publishedPages());

        $manager->save(FooterTestPages::page('p-home', 'startseite', 'Start'));
        $manager->publishedPages();
    }

    public function testHiddenPublishedPageIsNotFound(): void
    {
        $provider = $this->createStub(PageProviderInterface::class);
        $provider->method('findAllPublished')->willReturn([FooterTestPages::page('p-hidden', 'intern', 'Intern', visible: false)]);
        $manager = new PageManager($provider, $this->createStub(PageProcessorInterface::class), new InMemoryModelCache());

        $this->expectException(PageNotFoundException::class);
        $manager->getPublished('intern');
    }
}
