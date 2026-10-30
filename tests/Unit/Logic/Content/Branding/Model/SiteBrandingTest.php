<?php

namespace App\Tests\Unit\Logic\Content\Branding\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\Branding\Model\SiteBranding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SiteBrandingTest extends TestCase
{
    public function testFallsBackToDefaultLogo(): void
    {
        self::assertSame(SiteBranding::DEFAULT_LOGO_URL, (new SiteBranding(null))->logoUrl());
        self::assertNull((new SiteBranding('   '))->customLogoUrl);
    }

    public function testUsesTrimmedCustomLogo(): void
    {
        $branding = new SiteBranding(' /uploads/logos/waldbad.png ');

        self::assertSame('/uploads/logos/waldbad.png', $branding->customLogoUrl);
        self::assertSame('/uploads/logos/waldbad.png', $branding->logoUrl());
    }

    #[DataProvider('invalidUrls')]
    public function testRejectsForeignOrInvalidUrls(string $url): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new SiteBranding($url);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrls(): iterable
    {
        yield 'fremder Server' => ['https://example.test/logo.png'];
        yield 'protokollrelativ' => ['//example.test/logo.png'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'relativ ohne Schrägstrich' => ['uploads/logo.png'];
        yield 'Anführungszeichen' => ['/uploads/logo.png" onerror="x'];
        yield 'zu lang' => ['/uploads/'.str_repeat('a', SiteBranding::MAX_URL_LENGTH)];
    }
}
