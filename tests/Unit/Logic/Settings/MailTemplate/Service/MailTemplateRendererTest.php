<?php

namespace App\Tests\Unit\Logic\Settings\MailTemplate\Service;

use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\Service\MailContentRenderer;
use App\Logic\Settings\MailTemplate\Service\MailTemplateRenderer;
use PHPUnit\Framework\TestCase;

final class MailTemplateRendererTest extends TestCase
{
    public function testReplacesPlaceholdersInSubjectAndBody(): void
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate(
            MailTemplateKey::MembershipApplicationApproved,
            'Willkommen {{vorname}}',
            'Hallo {{vorname}} {{nachname}}, Beitrag: {{beitraege}}',
        ));

        $rendered = $this->renderer($manager)->render(MailTemplateKey::MembershipApplicationApproved, [
            'vorname' => 'Erika',
            'nachname' => 'Musterfrau',
            'beitraege' => '50,00 €',
        ]);

        self::assertSame('Willkommen Erika', $rendered['subject']);
        self::assertSame('Hallo Erika Musterfrau, Beitrag: 50,00 €', $rendered['body']);
        self::assertSame('<p style="margin:0 0 16px;">Hallo Erika Musterfrau, Beitrag: 50,00 €</p>', $rendered['html']);
    }

    public function testLeavesUnknownPlaceholdersUntouched(): void
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate(
            MailTemplateKey::MembershipApplicationApproved,
            'Betreff',
            'Hallo {{vorname}} {{tippfehler}}',
        ));

        $rendered = $this->renderer($manager)->render(MailTemplateKey::MembershipApplicationApproved, ['vorname' => 'Erika']);

        self::assertSame('Hallo Erika {{tippfehler}}', $rendered['body']);
    }

    public function testHtmlFragmentSplitsBlankLinesIntoSeparateParagraphs(): void
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate(
            MailTemplateKey::MembershipApplicationApproved,
            'Betreff',
            "Erster Absatz\n\nZweiter Absatz",
        ));

        $rendered = $this->renderer($manager)->render(MailTemplateKey::MembershipApplicationApproved, []);

        self::assertSame(
            '<p style="margin:0 0 16px;">Erster Absatz</p><p style="margin:0 0 16px;">Zweiter Absatz</p>',
            $rendered['html'],
        );
    }

    /**
     * `$htmlBlocks` überschreibt nur die HTML-Ansicht mit fertigem, unescaped HTML — Betreff und
     * Text-Fallback verwenden weiter den gleichnamigen Wert aus `$placeholders`.
     */
    public function testHtmlBlockOverridesOnlyTheHtmlRepresentationOfAPlaceholder(): void
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate(
            MailTemplateKey::MembershipApplicationApproved,
            'Betreff',
            "Beiträge:\n\n{{beitraege}}\n\nDanke, {{vorname}}.",
        ));

        $rendered = $this->renderer($manager)->render(
            MailTemplateKey::MembershipApplicationApproved,
            ['beitraege' => "- Position: 50,00 €\nGesamt: 50,00 €", 'vorname' => 'Erika'],
            ['beitraege' => '<ul><li>Position: 50,00 €</li></ul>'],
        );

        self::assertSame("Beiträge:\n\n- Position: 50,00 €\nGesamt: 50,00 €\n\nDanke, Erika.", $rendered['body']);
        self::assertSame(
            '<p style="margin:0 0 16px;">Beiträge:</p><ul><li>Position: 50,00 €</li></ul><p style="margin:0 0 16px;">Danke, Erika.</p>',
            $rendered['html'],
        );
    }

    private function renderer(MailTemplateManagerInterface $manager): MailTemplateRenderer
    {
        return new MailTemplateRenderer($manager, new MailContentRenderer());
    }
}
