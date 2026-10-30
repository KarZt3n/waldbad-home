<?php

namespace App\Data\Common\Sepa;

/**
 * Gemeinsame Formatierung für SEPA-XML-Dateien (Lastschrift und Überweisung). Texte werden auf den
 * SEPA-Basiszeichensatz reduziert (Umlaute umschreiben, übrige Sonderzeichen entfernen), da nicht
 * jede Bank den erweiterten deutschen Zeichensatz annimmt.
 */
final class SepaText
{
    public static function text(string $value, int $maxLength): string
    {
        $transliterated = strtr($value, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
            'é' => 'e', 'è' => 'e', 'á' => 'a', 'à' => 'a', 'ó' => 'o', 'ò' => 'o', 'ç' => 'c', 'ñ' => 'n',
            '&' => '+', '–' => '-', '—' => '-', '·' => ' ',
        ]);
        $allowed = (string) preg_replace("/[^A-Za-z0-9\\/\\-?:().,'+ ]/", '', $transliterated);

        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $allowed)), 0, $maxLength);
    }

    public static function amount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
