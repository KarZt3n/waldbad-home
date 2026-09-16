<?php

namespace App\Logic\IdentityAccess\Session\Dto;

/**
 * Ergebnis von `SessionTokenIssuer::issueInitial()`/`rotate()` — die beiden rohen (unverhashten)
 * Tokenwerte für die Cookies der aufrufenden Controller-Action, plus deren Ablaufzeitpunkte für die
 * Cookie-Lebensdauer.
 */
readonly class IssuedSession
{
    public function __construct(
        public string $rawAccessToken,
        public \DateTimeImmutable $accessTokenExpiresAt,
        public string $rawRefreshToken,
        public \DateTimeImmutable $refreshTokenExpiresAt,
    ) {
    }
}
