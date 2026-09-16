<?php

namespace App\Logic\IdentityAccess\Session\Manager;

use App\Logic\IdentityAccess\Session\AccessTokenProcessorInterface;
use App\Logic\IdentityAccess\Session\AccessTokenProviderInterface;
use App\Logic\IdentityAccess\Session\Model\AccessToken;

readonly class AccessTokenManager implements AccessTokenManagerInterface
{
    public function __construct(
        private AccessTokenProviderInterface $provider,
        private AccessTokenProcessorInterface $processor,
    ) {
    }

    public function findByHash(string $tokenHash): ?AccessToken
    {
        return $this->provider->findByHash($tokenHash);
    }

    public function save(AccessToken $token): AccessToken
    {
        return $this->processor->save($token);
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
    }
}
