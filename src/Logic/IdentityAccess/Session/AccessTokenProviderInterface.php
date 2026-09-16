<?php

namespace App\Logic\IdentityAccess\Session;

use App\Logic\IdentityAccess\Session\Model\AccessToken;

interface AccessTokenProviderInterface
{
    public function findByHash(string $tokenHash): ?AccessToken;
}
