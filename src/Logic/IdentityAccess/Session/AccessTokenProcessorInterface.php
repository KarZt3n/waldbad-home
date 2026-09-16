<?php

namespace App\Logic\IdentityAccess\Session;

use App\Logic\IdentityAccess\Session\Model\AccessToken;

interface AccessTokenProcessorInterface
{
    public function save(AccessToken $token): AccessToken;

    public function delete(string $id): void;
}
