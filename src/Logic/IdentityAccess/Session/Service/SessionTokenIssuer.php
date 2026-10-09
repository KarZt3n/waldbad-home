<?php

namespace App\Logic\IdentityAccess\Session\Service;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Common\SecureTokenGeneratorInterface;
use App\Logic\IdentityAccess\Session\Dto\IssuedSession;
use App\Logic\IdentityAccess\Session\Manager\AccessTokenManagerInterface;
use App\Logic\IdentityAccess\Session\Manager\LoginSessionManagerInterface;
use App\Logic\IdentityAccess\Session\Manager\RefreshTokenManagerInterface;
use App\Logic\IdentityAccess\Session\Model\AccessToken;
use App\Logic\IdentityAccess\Session\Model\LoginSession;
use App\Logic\IdentityAccess\Session\Model\LoginSessionEndReason;
use App\Logic\IdentityAccess\Session\Model\RefreshToken;

/**
 * Stellt Access- und Refresh-Token immer gemeinsam aus (siehe `RedeemLoginTokenUseCase`,
 * `RefreshSessionUseCase`) und kapselt damit die Lebensdauer-Regeln an einer Stelle:
 *
 * - Access-Token: 15 Minuten ab Ausstellung.
 * - Refresh-Token: gleitend 60 Minuten ab Ausstellung — passend zum Inaktivitäts-Logout der
 *   Redaktion (`assets/admin/shell.js`), damit eine Sitzung auch dann noch verlängert werden kann,
 *   wenn der Browser den Tab im Hintergrund gedrosselt oder eingefroren hat —, aber niemals über die
 *   bei der Erstanmeldung festgelegte absolute Obergrenze (`$absoluteExpiresAt`, 12 Stunden) hinaus.
 *   Das Deckeln passiert hier, nicht beim Aufrufer.
 *
 * Führt außerdem die Sitzungshistorie (`LoginSession`): Die Erstanmeldung legt sie an, jede Rotation
 * verlängert sie, `endSession()`/`endAllSessionsOfUser()` halten fest, wie sie endete.
 */
readonly class SessionTokenIssuer
{
    private const int ACCESS_TOKEN_MINUTES = 15;
    private const int REFRESH_TOKEN_SLIDING_MINUTES = 60;
    private const int REFRESH_TOKEN_ABSOLUTE_HOURS = 12;

    public function __construct(
        private AccessTokenManagerInterface $accessTokens,
        private RefreshTokenManagerInterface $refreshTokens,
        private LoginSessionManagerInterface $loginSessions,
        private SecureTokenGeneratorInterface $tokenGenerator,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function issueInitial(string $userId, ?string $userAgent = null): IssuedSession
    {
        $now = $this->clock->now();
        $absoluteExpiresAt = $now->modify('+'.self::REFRESH_TOKEN_ABSOLUTE_HOURS.' hours');
        $session = $this->loginSessions->save(new LoginSession(
            id: $this->identifierGenerator->generate(),
            userId: $userId,
            startedAt: $now,
            lastActivityAt: $now,
            expiresAt: $this->refreshTokenExpiresAt($now, $absoluteExpiresAt),
            userAgent: $userAgent,
        ));

        return $this->issue($userId, $now, $absoluteExpiresAt, $session->id);
    }

    public function rotate(RefreshToken $current): IssuedSession
    {
        $now = $this->clock->now();
        $issued = $this->issue($current->userId, $now, $current->absoluteExpiresAt, $current->sessionId);
        $session = $current->sessionId === null ? null : $this->loginSessions->find($current->sessionId);
        if ($session !== null) {
            $this->loginSessions->save($session->extend($now, $issued->refreshTokenExpiresAt));
        }

        return $issued;
    }

    public function endSession(RefreshToken $token, LoginSessionEndReason $reason): void
    {
        $session = $token->sessionId === null ? null : $this->loginSessions->find($token->sessionId);
        if ($session !== null && !$session->isEnded()) {
            $this->loginSessions->save($session->end($this->clock->now(), $reason));
        }
    }

    public function endAllSessionsOfUser(string $userId, LoginSessionEndReason $reason): void
    {
        $now = $this->clock->now();
        foreach ($this->loginSessions->findOpenForUser($userId) as $session) {
            $this->loginSessions->save($session->end($now, $reason));
        }
    }

    private function issue(string $userId, \DateTimeImmutable $now, \DateTimeImmutable $absoluteExpiresAt, ?string $sessionId): IssuedSession
    {
        $rawAccessToken = $this->tokenGenerator->generate();
        $accessTokenExpiresAt = $now->modify('+'.self::ACCESS_TOKEN_MINUTES.' minutes');
        $this->accessTokens->save(new AccessToken(
            id: $this->identifierGenerator->generate(),
            userId: $userId,
            tokenHash: hash('sha256', $rawAccessToken),
            expiresAt: $accessTokenExpiresAt,
            createdAt: $now,
        ));

        $rawRefreshToken = $this->tokenGenerator->generate();
        $refreshTokenExpiresAt = $this->refreshTokenExpiresAt($now, $absoluteExpiresAt);
        $this->refreshTokens->save(new RefreshToken(
            id: $this->identifierGenerator->generate(),
            userId: $userId,
            tokenHash: hash('sha256', $rawRefreshToken),
            expiresAt: $refreshTokenExpiresAt,
            absoluteExpiresAt: $absoluteExpiresAt,
            createdAt: $now,
            sessionId: $sessionId,
        ));

        return new IssuedSession($rawAccessToken, $accessTokenExpiresAt, $rawRefreshToken, $refreshTokenExpiresAt);
    }

    private function refreshTokenExpiresAt(\DateTimeImmutable $now, \DateTimeImmutable $absoluteExpiresAt): \DateTimeImmutable
    {
        return min($now->modify('+'.self::REFRESH_TOKEN_SLIDING_MINUTES.' minutes'), $absoluteExpiresAt);
    }
}
