<?php

namespace App\Logic\Membership\MemberAccess\Service;

/**
 * Hashing für das per Mail verschickte Zugangspasswort (siehe `MemberAccessToken::$passwordHash`,
 * `RandomAccessPasswordGenerator`) — wie beim PIN-Schutz (`PinHasher`) ein langsames
 * `password_hash`, da das 8-stellige Passwort (anders als der Token selbst) vergleichsweise wenig
 * Entropie hat und daher gegen Offline-Brute-Force auf die Datenbank geschützt werden muss.
 */
readonly class MemberAccessPasswordHasher
{
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
