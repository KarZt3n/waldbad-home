<?php

namespace App\Logic\IdentityAccess\Session\Manager;

use App\Logic\IdentityAccess\Session\Model\AccessToken;

interface AccessTokenManagerInterface
{
    public function findByHash(string $tokenHash): ?AccessToken;

    public function save(AccessToken $token): AccessToken;

    public function delete(string $id): void;
}
