<?php

namespace App\Logic\IdentityAccess\Session\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\IdentityAccess\Session\Dto\AuthenticatedSession;
use App\Logic\IdentityAccess\Session\Exception\InvalidSessionException;
use App\Logic\IdentityAccess\Session\Manager\RefreshTokenManagerInterface;
use App\Logic\IdentityAccess\Session\Model\RefreshToken;
use App\Logic\IdentityAccess\Session\Service\SessionTokenIssuer;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;

/**
 * Erneuert die Redaktions-Sitzung: rotiert den vorgelegten Refresh-Token (das bisherige Token wird
 * gesperrt, nicht gelöscht) und stellt ein neues Access-/Refresh-Token-Paar aus (siehe
 * `SessionTokenIssuer::rotate()`). Wird vom Frontend proaktiv alle ~10 Minuten aufgerufen, solange
 * die Redaktion geöffnet ist (siehe `assets/app.js`).
 *
 * Wird ein bereits rotiertes (gesperrtes) Token erneut vorgelegt, deutet das auf einen gestohlenen
 * Token hin — vorsorglich werden dann alle Refresh-Tokens des Benutzers gesperrt, was die gesamte
 * Sitzung überall beendet. Ausnahme: Wurde das Token erst vor Kurzem (siehe
 * `REUSE_GRACE_PERIOD_SECONDS`) rotiert, wird die Wiedervorlage als harmloser Wettlauf behandelt
 * (z. B. zwei gleichzeitig geöffnete Redaktions-Tabs, deren proaktive Refresh-Timer kollidieren) und
 * stattdessen einfach erneut ausgestellt, statt die Sitzung zu beenden.
 */
readonly class RefreshSessionUseCase
{
    private const int REUSE_GRACE_PERIOD_SECONDS = 30;

    public function __construct(
        private RefreshTokenManagerInterface $refreshTokens,
        private UserManagerInterface $users,
        private SessionTokenIssuer $issuer,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $rawRefreshToken): AuthenticatedSession
    {
        $now = $this->clock->now();
        $token = $this->refreshTokens->findByHash(hash('sha256', $rawRefreshToken)) ?? throw new InvalidSessionException();

        if ($token->isRevoked()) {
            if (!$this->isWithinReuseGracePeriod($token, $now)) {
                $this->refreshTokens->revokeAllForUser($token->userId, $now);
                throw new InvalidSessionException();
            }
        } elseif ($token->isExpired($now)) {
            $this->refreshTokens->revoke($token->id, $now);
            throw new InvalidSessionException();
        }

        $user = $this->users->get($token->userId);
        if (!$user->active) {
            if (!$token->isRevoked()) {
                $this->refreshTokens->revoke($token->id, $now);
            }
            throw new InvalidSessionException();
        }

        if (!$token->isRevoked()) {
            $this->refreshTokens->revoke($token->id, $now);
        }

        return new AuthenticatedSession($user, $this->issuer->rotate($token));
    }

    private function isWithinReuseGracePeriod(RefreshToken $token, \DateTimeImmutable $now): bool
    {
        return $token->revokedAt !== null
            && $now < $token->revokedAt->modify('+'.self::REUSE_GRACE_PERIOD_SECONDS.' seconds')
            && $now < $token->absoluteExpiresAt;
    }
}
