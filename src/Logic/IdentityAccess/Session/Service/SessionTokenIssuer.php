<?php

namespace App\Logic\IdentityAccess\Session\Service;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Common\SecureTokenGeneratorInterface;
use App\Logic\IdentityAccess\Session\Dto\IssuedSession;
use App\Logic\IdentityAccess\Session\Manager\AccessTokenManagerInterface;
use App\Logic\IdentityAccess\Session\Manager\RefreshTokenManagerInterface;
use App\Logic\IdentityAccess\Session\Model\AccessToken;
use App\Logic\IdentityAccess\Session\Model\RefreshToken;

/**
 * Stellt Access- und Refresh-Token immer gemeinsam aus (siehe `RedeemLoginTokenUseCase`,
 * `RefreshSessionUseCase`) und kapselt damit die Lebensdauer-Regeln an einer Stelle:
 *
 * - Access-Token: 15 Minuten ab Ausstellung.
 * - Refresh-Token: gleitend 15 Minuten ab Ausstellung, aber niemals über die bei der
 *   Erstanmeldung festgelegte absolute Obergrenze (`$absoluteExpiresAt`, 12 Stunden) hinaus — das
 *   Deckeln passiert hier, nicht beim Aufrufer.
 */
readonly class SessionTokenIssuer
{
    private const int ACCESS_TOKEN_MINUTES = 15;
    private const int REFRESH_TOKEN_SLIDING_MINUTES = 15;
    private const int REFRESH_TOKEN_ABSOLUTE_HOURS = 12;

    public function __construct(
        private AccessTokenManagerInterface $accessTokens,
        private RefreshTokenManagerInterface $refreshTokens,
        private SecureTokenGeneratorInterface $tokenGenerator,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function issueInitial(string $userId): IssuedSession
    {
        $now = $this->clock->now();

        return $this->issue($userId, $now, $now->modify('+'.self::REFRESH_TOKEN_ABSOLUTE_HOURS.' hours'));
    }

    public function rotate(RefreshToken $current): IssuedSession
    {
        return $this->issue($current->userId, $this->clock->now(), $current->absoluteExpiresAt);
    }

    private function issue(string $userId, \DateTimeImmutable $now, \DateTimeImmutable $absoluteExpiresAt): IssuedSession
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
        $refreshTokenExpiresAt = min($now->modify('+'.self::REFRESH_TOKEN_SLIDING_MINUTES.' minutes'), $absoluteExpiresAt);
        $this->refreshTokens->save(new RefreshToken(
            id: $this->identifierGenerator->generate(),
            userId: $userId,
            tokenHash: hash('sha256', $rawRefreshToken),
            expiresAt: $refreshTokenExpiresAt,
            absoluteExpiresAt: $absoluteExpiresAt,
            createdAt: $now,
        ));

        return new IssuedSession($rawAccessToken, $accessTokenExpiresAt, $rawRefreshToken, $refreshTokenExpiresAt);
    }
}
