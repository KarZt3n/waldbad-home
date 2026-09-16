<?php

namespace App\Logic\IdentityAccess\Session\Manager;

use App\Logic\IdentityAccess\Session\Model\RefreshToken;

interface RefreshTokenManagerInterface
{
    public function findByHash(string $tokenHash): ?RefreshToken;

    public function save(RefreshToken $token): RefreshToken;

    public function revoke(string $id, \DateTimeImmutable $revokedAt): void;

    public function revokeAllForUser(string $userId, \DateTimeImmutable $revokedAt): void;
}
