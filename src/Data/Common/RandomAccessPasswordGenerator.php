<?php

namespace App\Data\Common;

use App\Logic\Common\AccessPasswordGeneratorInterface;

/**
 * 5 Zeichen, nur Buchstaben und Ziffern (mindestens je eines davon), damit sich das Passwort ohne
 * Sonderzeichen bequem abtippen lässt — auch auf dem Handy. Die geringere Entropie ist vertretbar,
 * da es nur der zweite Faktor neben dem hochentropischen, 30 Minuten gültigen Link-Token ist und die
 * Passworteingabe je IP begrenzt wird. Ohne leicht verwechselbare Zeichen (0/O, 1/l/I); die
 * Position von Buchstabe und Ziffer wird zufällig verwürfelt.
 */
readonly class RandomAccessPasswordGenerator implements AccessPasswordGeneratorInterface
{
    private const string LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
    private const string DIGITS = '23456789';
    private const int LENGTH = 5;

    public function generate(): string
    {
        $alphanumeric = self::LETTERS.self::DIGITS;
        $characters = [self::randomCharacter(self::LETTERS), self::randomCharacter(self::DIGITS)];
        while (\count($characters) < self::LENGTH) {
            $characters[] = self::randomCharacter($alphanumeric);
        }

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
