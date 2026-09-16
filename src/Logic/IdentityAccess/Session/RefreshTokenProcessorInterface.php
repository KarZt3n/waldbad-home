<?php

namespace App\Logic\IdentityAccess\Session;

use App\Logic\IdentityAccess\Session\Model\RefreshToken;

interface RefreshTokenProcessorInterface
{
    public function save(RefreshToken $token): RefreshToken;

    public function revoke(string $id, \DateTimeImmutable $revokedAt): void;

    public function revokeAllForUser(string $userId, \DateTimeImmutable $revokedAt): void;
}
