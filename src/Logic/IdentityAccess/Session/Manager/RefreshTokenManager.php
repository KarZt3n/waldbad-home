<?php

namespace App\Logic\IdentityAccess\Session\Manager;

use App\Logic\IdentityAccess\Session\Model\RefreshToken;
use App\Logic\IdentityAccess\Session\RefreshTokenProcessorInterface;
use App\Logic\IdentityAccess\Session\RefreshTokenProviderInterface;

readonly class RefreshTokenManager implements RefreshTokenManagerInterface
{
    public function __construct(
        private RefreshTokenProviderInterface $provider,
        private RefreshTokenProcessorInterface $processor,
    ) {
    }

    public function findByHash(string $tokenHash): ?RefreshToken
    {
        return $this->provider->findByHash($tokenHash);
    }

    public function save(RefreshToken $token): RefreshToken
    {
        return $this->processor->save($token);
    }

    public function revoke(string $id, \DateTimeImmutable $revokedAt): void
    {
        $this->processor->revoke($id, $revokedAt);
    }

    public function revokeAllForUser(string $userId, \DateTimeImmutable $revokedAt): void
    {
        $this->processor->revokeAllForUser($userId, $revokedAt);
    }
}
