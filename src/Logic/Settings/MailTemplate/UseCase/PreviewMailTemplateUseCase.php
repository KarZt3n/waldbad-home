<?php

namespace App\Logic\Settings\MailTemplate\UseCase;

use App\Logic\Settings\Email\Model\AssociationName;
use App\Logic\Settings\MailTemplate\Dto\MailTemplatePreview;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\Service\BrandedEmailLayout;
use App\Logic\Settings\MailTemplate\Service\EmailLogoProviderInterface;
use App\Logic\Settings\MailTemplate\Service\MailTemplateRenderer;

/**
 * Rendert Betreff/Text/HTML einer Mailvorlage mit Beispieldaten (siehe
 * `MailTemplateKey::samplePlaceholders()`) — für den „Vorschau“-Button im Mailvorlagen-Editor.
 * Arbeitet bewusst auf dem übergebenen (ggf. noch ungespeicherten) Text und Signatur-Auswahl aus
 * der Oberfläche statt der gespeicherten Vorlage, damit die Vorschau auch unsaved Änderungen zeigt,
 * bevor gespeichert wird.
 */
readonly class PreviewMailTemplateUseCase
{
    public function __construct(
        private MailTemplateRenderer $renderer,
        private BrandedEmailLayout $layout,
        private EmailLogoProviderInterface $logoProvider,
    ) {
    }

    public function execute(MailTemplateKey $key, string $subject, string $body, ?string $signatureId): MailTemplatePreview
    {
        $rendered = $this->renderer->renderText($subject, $body, $key->samplePlaceholders(), $key->sampleHtmlBlocks(), $signatureId);
        $html = $this->layout->wrap($rendered['subject'], $rendered['html'], $this->logoProvider->getLogoDataUri(), AssociationName::CURRENT);

        return new MailTemplatePreview($rendered['subject'], $rendered['body'], $html);
    }
}
