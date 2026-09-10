<?php

namespace App\Data\Common;

use App\Logic\Common\AccessPasswordGeneratorInterface;

/**
 * 8 Zeichen: mindestens ein Buchstabe, mindestens eine Ziffer, genau 2 Sonderzeichen, Rest gemischt
 * alphanumerisch — Position der Zeichenarten wird zufällig verwürfelt, sonst stünden die
 * Sonderzeichen immer an denselben Stellen. Buchstaben/Ziffern ohne leicht verwechselbare Zeichen
 * (0/O, 1/l/I), da das Passwort von Hand aus der Mail abgetippt wird.
 */
readonly class RandomAccessPasswordGenerator implements AccessPasswordGeneratorInterface
{
    private const string LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
    private const string DIGITS = '23456789';
    private const string SPECIAL_CHARACTERS = '!@#$%&*+?';

    public function generate(): string
    {
        $alphanumeric = self::LETTERS.self::DIGITS;
        $characters = [
            self::randomCharacter(self::LETTERS),
            self::randomCharacter(self::DIGITS),
            self::randomCharacter($alphanumeric),
            self::randomCharacter($alphanumeric),
            self::randomCharacter($alphanumeric),
            self::randomCharacter($alphanumeric),
            self::randomCharacter(self::SPECIAL_CHARACTERS),
            self::randomCharacter(self::SPECIAL_CHARACTERS),
        ];

        return implode('', self::shuffle($characters));
    }

    private static function randomCharacter(string $alphabet): string
    {
        return $alphabet[random_int(0, \strlen($alphabet) - 1)];
    }

    /**
     * Fisher-Yates mit `random_int()` statt `str_shuffle()`/`shuffle()`, da letztere nicht
     * kryptographisch sicher sind.
     *
     * @param list<string> $characters
     * @return array<int, string>
     */
    private static function shuffle(array $characters): array
    {
        for ($i = \count($characters) - 1; $i > 0; --$i) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return $characters;
    }
}
