<?php

namespace App\Tests\Support;

use App\Logic\Common\AccessPasswordGeneratorInterface;

/**
 * Ersetzt im Testumfeld den echten, zufälligen Passwort-Generator (siehe
 * config/services_test.yaml) mit einem festen, deterministischen Wert — analog zu
 * `FixedSecureTokenGenerator`, damit Funktionstests den zweiten Faktor (siehe `MemberAccessToken`)
 * ebenfalls vorhersagen können.
 */
readonly class FixedAccessPasswordGenerator implements AccessPasswordGeneratorInterface
{
    public const string PASSWORD = 'aB3!xy9?';

    public function generate(): string
    {
        return self::PASSWORD;
    }
}
