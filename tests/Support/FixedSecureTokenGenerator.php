<?php

namespace App\Tests\Support;

use App\Logic\Common\SecureTokenGeneratorInterface;

/**
 * Ersetzt im Testumfeld den echten, zufälligen Generator (siehe config/services_test.yaml) mit
 * einem festen, deterministischen Wert — Funktionstests können den erzeugten Zugangs-Token damit
 * vorhersagen und den kompletten Magic-Link-Ablauf (Anfordern → Einlösen) über echte HTTP-Aufrufe
 * testen, ohne die tatsächlich verschickte E-Mail abfangen zu müssen (siehe `NotificationMailer`,
 * die best effort und ohne Rückgabewert verschickt).
 */
readonly class FixedSecureTokenGenerator implements SecureTokenGeneratorInterface
{
    public const string TOKEN = 'test-fixed-member-access-token';

    public function generate(): string
    {
        return self::TOKEN;
    }
}
