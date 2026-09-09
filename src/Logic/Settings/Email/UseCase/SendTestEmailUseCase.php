<?php

namespace App\Logic\Settings\Email\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Service\ConfiguredMailTransportFactory;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Verschickt anhand der aktuell gespeicherten `EmailSettings` eine Testmail an eine frei angegebene
 * Adresse — damit lässt sich die Konfiguration prüfen, ohne auf das erste echte Ereignis (z. B.
 * einen Mitgliedsantrag) warten zu müssen. Anders als `NotificationMailer::notify()` wird ein
 * Fehlschlag hier nicht verschluckt, sondern als fachliche Meldung nach oben gereicht.
 */
readonly class SendTestEmailUseCase
{
    public function __construct(
        private EmailSettingsManagerInterface $manager,
        private ConfiguredMailTransportFactory $transportFactory,
    ) {
    }

    public function execute(string $to): void
    {
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessRuleViolationException('Die Empfänger-E-Mail-Adresse ist ungültig.');
        }

        $settings = $this->manager->get();
        $transport = $this->transportFactory->create($settings);
        $email = (new Email())
            ->from(new Address((string) $settings->fromAddress, (string) ($settings->fromName ?? '')))
            ->to($to)
            ->subject('Testmail der Waldbad-Vereinsverwaltung')
            ->text('Diese Testmail bestätigt, dass die E-Mail-Einstellungen funktionieren.');

        try {
            $transport->send($email);
        } catch (TransportExceptionInterface $exception) {
            throw new BusinessRuleViolationException(
                'Die Testmail konnte nicht gesendet werden: '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }
}
