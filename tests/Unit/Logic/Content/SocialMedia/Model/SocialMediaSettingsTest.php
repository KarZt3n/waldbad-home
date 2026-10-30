<?php

namespace App\Tests\Unit\Logic\Content\SocialMedia\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\SocialMedia\Model\SocialMediaLink;
use App\Logic\Content\SocialMedia\Model\SocialMediaSettings;
use App\Logic\Content\SocialMedia\Model\SocialPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SocialMediaSettingsTest extends TestCase
{
    public function testPublicLinksAreEmptyWhileHidden(): void
    {
        $links = [new SocialMediaLink(SocialPlatform::Instagram, 'https://www.instagram.com/waldbad')];

        self::assertSame([], (new SocialMediaSettings(false, $links))->publicLinks());
        self::assertSame($links, (new SocialMediaSettings(true, $links))->publicLinks());
    }

    public function testRejectsDuplicatePlatforms(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Facebook ist mehrfach eingetragen.');

        new SocialMediaSettings(true, [
            new SocialMediaLink(SocialPlatform::Facebook, 'https://www.facebook.com/a'),
            new SocialMediaLink(SocialPlatform::Facebook, 'https://www.facebook.com/b'),
        ]);
    }

    public function testLinkUrlIsTrimmed(): void
    {
        self::assertSame('https://www.facebook.com/waldbad', (new SocialMediaLink(SocialPlatform::Facebook, '  https://www.facebook.com/waldbad '))->url);
    }

    #[DataProvider('invalidUrls')]
    public function testRejectsInvalidUrls(string $url): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new SocialMediaLink(SocialPlatform::Instagram, $url);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrls(): iterable
    {
        yield 'leer' => ['   '];
        yield 'ohne Schema' => ['www.instagram.com/waldbad'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'ftp' => ['ftp://example.test/waldbad'];
        yield 'zu lang' => ['https://example.test/'.str_repeat('a', SocialMediaLink::MAX_URL_LENGTH)];
    }
}
