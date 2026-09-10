<?php

namespace App\Logic\Membership\MemberAccess;

use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;

interface MemberAccessTokenProviderInterface
{
    public function findByHash(string $tokenHash): ?MemberAccessToken;
}
