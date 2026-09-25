<?php

namespace App\Logic\Settings\Email\Service;

use App\Logic\Settings\Email\Model\AssociationName;
use App\Logic\Settings\MailTemplate\Service\BrandedEmailLayout;
use App\Logic\Settings\MailTemplate\Service\EmailLogoProviderInterface;
use App\Logic\Settings\MailTemplate\Service\MailContentRenderer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Verschickt eine von der Verwaltung frei formulierte Mail (Betreff/Text selbst getippt, keine
 * Mailvorlage) im Vereinslayout an genau einen Empfänger — z. B. Helfer-Rundmails oder Antworten
 * auf Mitgliedernachrichten. Fehler beim Versand werden an den Aufrufer weitergereicht; der
 * entscheidet, ob er sie protokolliert oder abbricht.
 */
readonly class FreeTextMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private MailContentRenderer $contentRenderer,
        private BrandedEmailLayout $layout,
        private EmailLogoProviderInterface $logoProvider,
        private string $fromAddress,
        private ?string $fromName,
    ) {
    }

    public function send(string $recipient, string $subject, string $body): void
    {
        $html = $this->layout->wrap(
            $subject,
            $this->contentRenderer->toHtmlFragment($body),
            $this->logoProvider->getLogoDataUri(),
            AssociationName::CURRENT,
        );

        $this->mailer->send((new Email())
            ->from(new Address($this->fromAddress, $this->fromName ?? ''))
            ->to($recipient)
            ->subject($subject)
            ->text($body)
            ->html($html));
    }
}
