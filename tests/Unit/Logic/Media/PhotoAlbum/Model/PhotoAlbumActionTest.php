<?php

namespace App\Tests\Unit\Logic\Media\PhotoAlbum\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbumAction;
use PHPUnit\Framework\TestCase;

final class PhotoAlbumActionTest extends TestCase
{
    public function testAcceptsUrlOrPageTarget(): void
    {
        self::assertSame('https://photos.google.com/share/x', (new PhotoAlbumAction('a1', 0, ' Öffnen ', 'https://photos.google.com/share/x', null))->url);
        self::assertSame('page-1', (new PhotoAlbumAction('a2', 1, 'Ergebnisse', null, 'page-1'))->pageId);
        self::assertSame('https://my.raceresult.com/1', (new PhotoAlbumAction('a3', 2, 'Ergebnisse', 'my.raceresult.com/1', null))->url);
    }

    public function testRejectsMissingTarget(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new PhotoAlbumAction('a1', 0, 'Öffnen', null, null);
    }

    public function testRejectsBothTargets(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new PhotoAlbumAction('a1', 0, 'Öffnen', 'https://example.test', 'page-1');
    }

    public function testRejectsScriptUrls(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new PhotoAlbumAction('a1', 0, 'Öffnen', 'javascript:alert(1)', null);
    }
}
