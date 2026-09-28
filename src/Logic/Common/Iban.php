<?php

namespace App\Logic\Common;

/**
 * Formale IBAN-Prüfung (Aufbau + Prüfziffer nach ISO 13616, Modulo 97) — gemeinsam genutzt von
 * Mitgliedern, Mitgliedsanträgen und den SEPA-Gläubigerdaten.
 */
final class Iban
{
    public static function normalize(string $iban): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $iban));
    }

    public static function isValid(string $iban): bool
    {
        $normalized = self::normalize($iban);
        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $normalized) !== 1) {
            return false;
        }

        $rearranged = substr($normalized, 4).substr($normalized, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $character) {
            $numeric .= ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
        }
        $remainder = 0;
        foreach (str_split($numeric) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder === 1;
    }
}
