<?php

namespace App\Tests\Unit\Logic\Settings\MailTemplate\Service;

use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;
use App\Logic\Settings\MailSignature\Model\MailSignature;
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

    /**
     * Eine der Vorlage zugeordnete Signatur (siehe `MailTemplate::$signatureId`) wird — mit
     * denselben Platzhaltern ersetzt — an Text und HTML angehängt, statt ihren Text in die Vorlage
     * zu kopieren: eine spätere Änderung der Signatur wirkt sich so automatisch aus.
     */
    public function testAppendsTheAssignedSignatureWithPlaceholdersReplaced(): void
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate(
            MailTemplateKey::MembershipApplicationApproved,
            'Betreff',
            'Hallo {{vorname}}.',
            'signature-1',
        ));
        $signatures = $this->createStub(MailSignatureManagerInterface::class);
        $signatures->method('find')->willReturn(new MailSignature('signature-1', 'Standard', "Viele Grüße\n{{vereinsname}}"));

        $rendered = $this->renderer($manager, $signatures)->render(MailTemplateKey::MembershipApplicationApproved, [
            'vorname' => 'Erika',
            'vereinsname' => 'Naturbad Borkheide e.V.',
        ]);

        self::assertSame("Hallo Erika.\n\nViele Grüße\nNaturbad Borkheide e.V.", $rendered['body']);
        self::assertStringContainsString('Hallo Erika.', $rendered['html']);
        self::assertStringContainsString('Naturbad Borkheide e.V.', $rendered['html']);
    }

    /**
     * Wurde die zugeordnete Signatur inzwischen gelöscht, wird einfach nichts angehängt — best
     * effort, kein Fehler (siehe `MailTemplateRenderer::renderText()`).
     */
    public function testSilentlySkipsAnAssignedSignatureThatNoLongerExists(): void
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate(
            MailTemplateKey::MembershipApplicationApproved,
            'Betreff',
            'Hallo {{vorname}}.',
            'deleted-signature',
        ));
        $signatures = $this->createStub(MailSignatureManagerInterface::class);
        $signatures->method('find')->willReturn(null);

        $rendered = $this->renderer($manager, $signatures)->render(MailTemplateKey::MembershipApplicationApproved, ['vorname' => 'Erika']);

        self::assertSame('Hallo Erika.', $rendered['body']);
    }

    private function renderer(MailTemplateManagerInterface $manager, ?MailSignatureManagerInterface $signatures = null): MailTemplateRenderer
    {
        return new MailTemplateRenderer($manager, new MailContentRenderer(), $signatures ?? $this->noSignature());
    }

    private function noSignature(): MailSignatureManagerInterface
    {
        $signatures = $this->createStub(MailSignatureManagerInterface::class);
        $signatures->method('find')->willReturn(null);

        return $signatures;
    }
}
