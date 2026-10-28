<?php

namespace App\Tests\Unit\Logic\Content\Page\Model;

use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\ContentCollectionItem;
use PHPUnit\Framework\TestCase;

final class ContentBlockMediaReplacementTest extends TestCase
{
    private const string URL = '/uploads/media/alt.png';

    public function testReplacementKeepsAltTextAndSource(): void
    {
        $block = new ContentBlock(type: ContentBlockType::Image, content: '', mediaUrl: self::URL, mediaAlt: 'Teich', mediaSource: 'Foto: Verein', layout: 'center');

        $replaced = $block->withMediaReplaced(self::URL, '/uploads/media/neu.png');

        self::assertNotNull($replaced);
        self::assertSame('/uploads/media/neu.png', $replaced->mediaUrl);
        self::assertSame('Teich', $replaced->mediaAlt);
        self::assertSame('Foto: Verein', $replaced->mediaSource);
        self::assertSame('center', $replaced->layout);
    }

    public function testRemovingTheImageDropsPureImageBlocks(): void
    {
        $block = new ContentBlock(type: ContentBlockType::Image, content: '', mediaUrl: self::URL);

        self::assertNull($block->withMediaReplaced(self::URL, null));
    }

    public function testRemovingTheImageTurnsImageTextIntoRichText(): void
    {
        $block = new ContentBlock(type: ContentBlockType::ImageText, content: '<p>Text</p>', mediaUrl: self::URL, layout: 'image_left');

        $replaced = $block->withMediaReplaced(self::URL, null);

        self::assertNotNull($replaced);
        self::assertSame(ContentBlockType::RichText, $replaced->type);
        self::assertSame('<p>Text</p>', $replaced->content);
        self::assertNull($replaced->mediaUrl);
    }

    public function testRemovingTheImageKeepsOptionalTeaserImageBlocks(): void
    {
        $block = new ContentBlock(type: ContentBlockType::PageTeaser, content: '', mediaUrl: self::URL, mediaAlt: 'Teich', layout: 'image_right', embeddedPageId: 'page-1');

        $replaced = $block->withMediaReplaced(self::URL, null);

        self::assertNotNull($replaced);
        self::assertSame(ContentBlockType::PageTeaser, $replaced->type);
        self::assertNull($replaced->mediaUrl);
        self::assertNull($replaced->mediaAlt);
    }

    public function testRemovingTheImageClearsCollectionItemsOnly(): void
    {
        $block = new ContentBlock(
            type: ContentBlockType::FeatureCollection,
            content: 'Unsere Angebote',
            collectionColumns: 2,
            collectionItems: [
                new ContentCollectionItem(title: 'Teich', content: '', mediaUrl: self::URL, mediaAlt: 'Teich', mediaSource: 'Foto'),
                new ContentCollectionItem(title: 'Wiese', content: '', mediaUrl: '/uploads/media/wiese.png'),
            ],
        );

        self::assertSame([self::URL, '/uploads/media/wiese.png'], $block->mediaUrls());
        $replaced = $block->withMediaReplaced(self::URL, null);

        self::assertNotNull($replaced);
        self::assertNull($replaced->collectionItems[0]->mediaUrl);
        self::assertNull($replaced->collectionItems[0]->mediaSource);
        self::assertSame('/uploads/media/wiese.png', $replaced->collectionItems[1]->mediaUrl);
    }

    public function testUnrelatedBlocksStayUntouched(): void
    {
        $block = new ContentBlock(type: ContentBlockType::Image, content: '', mediaUrl: '/uploads/media/anderes.png');

        self::assertSame($block, $block->withMediaReplaced(self::URL, null));
    }
}
