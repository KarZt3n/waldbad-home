<?php

namespace App\Tests\Unit\Logic\Content\Footer\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\Footer\Manager\FooterNavigationManagerInterface;
use App\Logic\Content\Footer\Model\FooterNavigation;
use App\Logic\Content\Footer\UseCase\UpdateFooterNavigationUseCase;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Tests\Unit\Logic\Content\Footer\FooterTestPages;
use PHPUnit\Framework\TestCase;

final class UpdateFooterNavigationUseCaseTest extends TestCase
{
    public function testSavesPagesInGivenOrder(): void
    {
        $manager = $this->createMock(FooterNavigationManagerInterface::class);
        $manager->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (FooterNavigation $navigation): bool => $navigation->pageIds === ['p-b', 'p-a']))
            ->willReturnArgument(0);

        (new UpdateFooterNavigationUseCase($manager, $this->pageManager()))->execute(['p-b', 'p-a']);
    }

    public function testRejectsUnknownPage(): void
    {
        $manager = $this->createMock(FooterNavigationManagerInterface::class);
        $manager->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        (new UpdateFooterNavigationUseCase($manager, $this->pageManager()))->execute(['p-a', 'p-unknown']);
    }

    private function pageManager(): PageManagerInterface
    {
        $pageManager = $this->createStub(PageManagerInterface::class);
        $pageManager->method('all')->willReturn([
            FooterTestPages::page('p-a', 'a', 'A'),
            FooterTestPages::page('p-b', 'b', 'B'),
        ]);

        return $pageManager;
    }
}
