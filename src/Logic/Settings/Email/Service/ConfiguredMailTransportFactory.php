<?php

namespace App\Logic\Settings\Email\Service;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\Email\Model\EmailSettings;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Baut einen SMTP-Versandweg direkt aus den in der Oberfläche hinterlegten `EmailSettings` — anders
 * als der Standard-Mailer der Anwendung (`framework.mailer.dsn`, aus `.env`/Deployment-Konfiguration
 * fest verdrahtet) lässt sich dieser hier zur Laufzeit von Admin/Super-Admin über „Einstellungen“ →
 * „E-Mail-Einstellungen“ ändern, ohne Deployment.
 *
 * TLS/STARTTLS wird bewusst nicht als eigenes Feld abgefragt: Symfonys SMTP-Transport entscheidet
 * das automatisch anhand des Ports (465 = implizites TLS, sonst STARTTLS, falls vom Server
 * angeboten) — für die in `EmailProviderPreset` vorgesehenen Anbieter (Port 587) genügt das.
 */
readonly class ConfiguredMailTransportFactory
{
    public function create(EmailSettings $settings): TransportInterface
    {
        if (!$settings->isConfigured()) {
            throw new BusinessRuleViolationException('Für den Mailversand sind mindestens Server und Absender-E-Mail-Adresse erforderlich.');
        }

        $dsn = new Dsn(
            scheme: 'smtp',
            host: (string) $settings->host,
            user: $settings->username,
            password: $settings->password,
            port: $settings->port,
        );

        return (new Transport(iterator_to_array(Transport::getDefaultFactories())))->fromDsnObject($dsn);
    }
}
