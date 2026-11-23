<?php

namespace App\Tests\Unit\Logic\Content\Footer\Manager;

use App\Logic\Content\Footer\FooterNavigationProcessorInterface;
use App\Logic\Content\Footer\FooterNavigationProviderInterface;
use App\Logic\Content\Footer\Manager\FooterNavigationManager;
use App\Logic\Content\Footer\Model\FooterNavigation;
use App\Tests\Support\InMemoryModelCache;
use PHPUnit\Framework\TestCase;

final class FooterNavigationManagerTest extends TestCase
{
    public function testCachesAlsoTheNeverSavedStateAndInvalidatesOnSave(): void
    {
        $provider = $this->createMock(FooterNavigationProviderInterface::class);
        $provider->expects(self::exactly(2))->method('get')->willReturnOnConsecutiveCalls(null, new FooterNavigation(['p-a']));
        $processor = $this->createStub(FooterNavigationProcessorInterface::class);
        $processor->method('save')->willReturnArgument(0);
        $manager = new FooterNavigationManager($provider, $processor, new InMemoryModelCache());

        self::assertNull($manager->get());
        self::assertNull($manager->get());

        $manager->save(new FooterNavigation(['p-a']));
        self::assertSame(['p-a'], $manager->get()?->pageIds);
        self::assertSame(['p-a'], $manager->get()?->pageIds);
    }
}
