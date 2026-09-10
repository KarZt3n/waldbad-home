<?php

namespace App\Tests\Unit\Logic\Settings\MailTemplate\UseCase;

use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;
use App\Logic\Settings\MailSignature\Model\MailSignature;
use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\Service\BrandedEmailLayout;
use App\Logic\Settings\MailTemplate\Service\EmailLogoProviderInterface;
use App\Logic\Settings\MailTemplate\Service\MailContentRenderer;
use App\Logic\Settings\MailTemplate\Service\MailTemplateRenderer;
use App\Logic\Settings\MailTemplate\UseCase\PreviewMailTemplateUseCase;
use PHPUnit\Framework\TestCase;

final class PreviewMailTemplateUseCaseTest extends TestCase
{
    /**
     * Die Vorschau rendert den übergebenen (ggf. noch ungespeicherten) Text aus dem Editor — nicht
     * die gespeicherte Vorlage — mit den Beispieldaten aus `MailTemplateKey::samplePlaceholders()`.
     */
    public function testRendersTheGivenTextWithSampleDataInsteadOfTheStoredTemplate(): void
    {
        $useCase = $this->useCase();

        $preview = $useCase->execute(
            MailTemplateKey::MembershipApplicationApproved,
            'Hallo {{vorname}}',
            'Willkommen {{vorname}} {{nachname}}, deine Nummer ist {{mitgliedsnummer}}.',
            null,
        );

        self::assertSame('Hallo Erika', $preview->subject);
        self::assertSame('Willkommen Erika Musterfrau, deine Nummer ist Bad-01234.', $preview->text);
        self::assertStringContainsString('Willkommen Erika Musterfrau', $preview->html);
        // Der gestaltete Rahmen (Logo/Farben/Fußzeile) wird mitgerendert, siehe BrandedEmailLayout.
        self::assertStringContainsString('<!doctype html>', $preview->html);
    }

    public function testRendersTheNestedContributionListForItsSampleHtmlBlock(): void
    {
        $preview = $this->useCase()->execute(MailTemplateKey::MembershipApplicationApproved, 'Betreff', "Beiträge:\n\n{{beitraege}}", null);

        self::assertStringContainsString('<ul', $preview->html);
        self::assertStringContainsString('Familienbeitrag Erwachsene', $preview->html);
        self::assertStringNotContainsString('{{beitraege}}', $preview->html);
    }

    /**
     * Die im Editor gerade ausgewählte (ggf. noch ungespeicherte) Signatur wird ebenfalls mit
     * Beispieldaten gerendert und angehängt — dieselbe Zuordnung, die später beim echten Versand
     * greift (siehe `MailTemplateRenderer`).
     */
    public function testAppendsTheGivenSignatureWithSampleDataReplaced(): void
    {
        $signatures = $this->createStub(MailSignatureManagerInterface::class);
        $signatures->method('find')->willReturn(new MailSignature('signature-1', 'Standard', 'Viele Grüße, {{vorname}}'));

        $preview = $this->useCase($signatures)->execute(MailTemplateKey::MembershipApplicationApproved, 'Betreff', 'Hallo!', 'signature-1');

        self::assertStringContainsString('Viele Grüße, Erika', $preview->text);
    }

    private function useCase(?MailSignatureManagerInterface $signatures = null): PreviewMailTemplateUseCase
    {
        $noSignature = $this->createStub(MailSignatureManagerInterface::class);
        $noSignature->method('find')->willReturn(null);

        $renderer = new MailTemplateRenderer(
            $this->createStub(MailTemplateManagerInterface::class),
            new MailContentRenderer(),
            $signatures ?? $noSignature,
        );

        return new PreviewMailTemplateUseCase($renderer, new BrandedEmailLayout(), $this->noLogo());
    }

    private function noLogo(): EmailLogoProviderInterface
    {
        $provider = $this->createStub(EmailLogoProviderInterface::class);
        $provider->method('getLogoDataUri')->willReturn(null);

        return $provider;
    }
}
