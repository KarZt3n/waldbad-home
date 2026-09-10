<?php

namespace App\Logic\Membership\MemberAccess;

use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;

interface MemberAccessTokenProcessorInterface
{
    public function save(MemberAccessToken $token): MemberAccessToken;
}
