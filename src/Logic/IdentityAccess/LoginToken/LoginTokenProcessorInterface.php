<?php

namespace App\Logic\IdentityAccess\LoginToken;

use App\Logic\IdentityAccess\LoginToken\Model\LoginToken;

interface LoginTokenProcessorInterface
{
    public function save(LoginToken $token): LoginToken;

    public function markConsumed(string $id, \DateTimeImmutable $consumedAt): void;
}
