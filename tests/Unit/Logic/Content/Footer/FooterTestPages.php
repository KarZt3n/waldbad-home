<?php

namespace App\Tests\Unit\Logic\Content\Footer;

use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;

final class FooterTestPages
{
    public static function page(string $id, string $slug, string $label, bool $visible = true): Page
    {
        $now = new \DateTimeImmutable('2026-11-24T10:00:00+01:00');

        return new Page(
            id: $id,
            title: $label,
            slug: $slug,
            navigationLabel: $label,
            parentId: null,
            blocks: [new ContentBlock(ContentBlockType::RichText, '<p>Inhalt</p>')],
            status: PageStatus::Published,
            visible: $visible,
            showInNavigation: false,
            navigationPosition: 0,
            seoTitle: $label,
            seoDescription: null,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
            publishedAt: $now,
        );
    }
}
