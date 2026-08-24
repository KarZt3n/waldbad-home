<?php

namespace App\Tests\Unit\Logic\Content\Page\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use PHPUnit\Framework\TestCase;

final class ContentBlockTest extends TestCase
{
    public function testPageTeaserAllowsAnOptionalImage(): void
    {
        $block = new ContentBlock(
            type: ContentBlockType::PageTeaser,
            content: '<p>Gemeinsam das Waldbad unterstützen.</p>',
            linkLabel: 'Mehr erfahren',
            layout: 'image_left',
            imageWidthPercent: 50,
            verticalAlignment: 'center',
            textAlignment: 'left',
            imageFit: 'cover',
            embeddedPageId: 'membership-page',
        );

        self::assertNull($block->mediaUrl);
        self::assertSame('membership-page', $block->embeddedPageId);
    }

    public function testPageTeaserRequiresATargetPage(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Zielseite');

        new ContentBlock(
            type: ContentBlockType::PageTeaser,
            content: '<p>Teaser</p>',
            layout: 'image_left',
        );
    }

    public function testImageSourceLengthIsLimited(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Bildquelle');

        new ContentBlock(
            type: ContentBlockType::Image,
            content: '',
            mediaUrl: '/bild.jpg',
            mediaSource: str_repeat('a', 301),
        );
    }
}
