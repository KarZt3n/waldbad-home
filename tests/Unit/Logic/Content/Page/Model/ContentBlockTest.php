<?php

namespace App\Tests\Unit\Logic\Content\Page\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\ContentCollectionItem;
use App\Logic\Content\Page\Model\EventCallToAction;
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

    public function testFeatureCollectionKeepsColumnsAndItems(): void
    {
        $item = new ContentCollectionItem(
            title: '1000 m² Badeteich',
            content: '<p>Viel Platz zum Schwimmen.</p>',
            mediaUrl: '/uploads/media/badeteich.jpg',
            mediaAlt: 'Badeteich im Waldbad',
            mediaSource: 'Foto: Naturbad Borkheide e.V.',
        );

        $block = new ContentBlock(
            type: ContentBlockType::FeatureCollection,
            content: 'Wir bieten unseren Besuchern',
            collectionColumns: 3,
            collectionItems: [$item],
        );

        self::assertSame(3, $block->collectionColumns);
        self::assertSame([$item], $block->collectionItems);
    }

    public function testFeatureCollectionRequiresAtLeastOneItem(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('mindestens einen Eintrag');

        new ContentBlock(
            type: ContentBlockType::FeatureCollection,
            content: 'Wir bieten unseren Besuchern',
            collectionColumns: 3,
        );
    }

    public function testFeatureCollectionLimitsColumns(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Spalten');

        new ContentBlock(
            type: ContentBlockType::FeatureCollection,
            content: 'Wir bieten unseren Besuchern',
            collectionColumns: 5,
            collectionItems: [new ContentCollectionItem('Badeteich', '')],
        );
    }

    public function testEventKeepsAdditionalCallToActions(): void
    {
        $action = new EventCallToAction('Mehr erfahren', null, 'information-page');
        $block = new ContentBlock(
            type: ContentBlockType::Event,
            content: '',
            eventTitle: 'Sommerfest',
            eventDate: '2026-08-15',
            eventTime: '14:00',
            eventCallToActions: [$action],
        );

        self::assertSame([$action], $block->eventCallToActions);
    }

    public function testEventCallToActionRequiresExactlyOneTarget(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('entweder auf eine URL oder auf eine Seite');

        new EventCallToAction('Mehr erfahren', '/veranstaltungen', 'event-page');
    }

    public function testEventCallToActionRejectsUnsafeUrlScheme(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('kein erlaubtes Ziel');

        new EventCallToAction('Mehr erfahren', 'javascript:alert(1)', null);
    }

    public function testEventCallToActionAddsHttpsToDomainWithoutScheme(): void
    {
        $action = new EventCallToAction('Google öffnen', 'google.com', null);

        self::assertSame('https://google.com', $action->url);
    }
}
