<?php

namespace App\Tests\Unit\Logic\Content\Page\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;
use PHPUnit\Framework\TestCase;

final class PageCompletenessTest extends TestCase
{
    public function testDraftWithIncompleteBlockCannotBeSubmittedForReview(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Block 2: Ein Call-to-Action benötigt Link und Beschriftung.');

        $this->draft()->requestReview(new \DateTimeImmutable('2026-11-24T10:00:00+01:00'));
    }

    public function testDraftWithIncompleteBlockCannotBePublished(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Block 2:');

        $this->draft()->publish(new \DateTimeImmutable('2026-11-24T10:00:00+01:00'));
    }

    private function draft(): Page
    {
        $now = new \DateTimeImmutable('2026-11-24T09:00:00+01:00');

        return new Page(
            id: 'p-1',
            title: 'Mitmachen',
            slug: 'mitmachen',
            navigationLabel: 'Mitmachen',
            parentId: null,
            blocks: [
                new ContentBlock(ContentBlockType::RichText, '<p>Inhalt</p>'),
                new ContentBlock(ContentBlockType::CallToAction, '<p>Jetzt mitmachen</p>'),
            ],
            status: PageStatus::Draft,
            visible: true,
            showInNavigation: true,
            navigationPosition: 0,
            seoTitle: 'Mitmachen',
            seoDescription: null,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
            publishedAt: null,
        );
    }
}
