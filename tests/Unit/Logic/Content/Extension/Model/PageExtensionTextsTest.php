<?php

namespace App\Tests\Unit\Logic\Content\Extension\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\Extension\Model\PageExtension;
use App\Logic\Content\Extension\Model\PageExtensionTexts;
use PHPUnit\Framework\TestCase;

final class PageExtensionTextsTest extends TestCase
{
    /** Nur Abweichungen werden gespeichert: ein auf den Standard zurückgesetzter Text folgt künftigen Änderungen im Code. */
    public function testKeepsOnlyTextsThatDifferFromTheDefault(): void
    {
        $texts = (new PageExtensionTexts(PageExtension::MembershipApplication))->withTexts([
            'title' => 'Mitglied werden',
            'eyebrow' => 'Naturbad Borkheide e.V.',
        ]);

        self::assertSame(['title' => 'Mitglied werden'], $texts->overrides);
        self::assertSame('Mitglied werden', $texts->resolved()['title']);
        self::assertTrue($texts->isDefault('eyebrow'));

        $reverted = $texts->withTexts(['title' => 'Beitrittserklärung']);
        self::assertSame([], $reverted->overrides);
    }

    public function testConsentVersionChangesOnlyWithTheConsentTexts(): void
    {
        $default = new PageExtensionTexts(PageExtension::MembershipApplication);

        self::assertSame($default->consentVersion(), $default->withTexts(['title' => 'Mitglied werden'])->consentVersion());
        self::assertNotSame($default->consentVersion(), $default->withTexts(['consentEmail' => 'Ich möchte den Newsletter.'])->consentVersion());
        self::assertNull((new PageExtensionTexts(PageExtension::Sauna))->consentVersion());
    }

    public function testIgnoresStoredTextsOfFieldsNoLongerKnown(): void
    {
        $texts = new PageExtensionTexts(PageExtension::MembershipApplication, ['removedField' => 'Alt']);

        self::assertSame([], $texts->overrides);
    }

    public function testRejectsLineBreaksInSingleLineFields(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('einzeilig');

        (new PageExtensionTexts(PageExtension::MembershipApplication))->withTexts(['title' => "Zeile 1\nZeile 2"]);
    }

    public function testNormalizesLineBreaksInMultilineFields(): void
    {
        $texts = (new PageExtensionTexts(PageExtension::MembershipApplication))->withTexts(['intro' => "  Absatz 1\r\nAbsatz 2  "]);

        self::assertSame("Absatz 1\nAbsatz 2", $texts->text('intro'));
    }
}
