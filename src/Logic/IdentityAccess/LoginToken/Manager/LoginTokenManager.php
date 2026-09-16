<?php

namespace App\Logic\IdentityAccess\LoginToken\Manager;

use App\Logic\IdentityAccess\LoginToken\LoginTokenProcessorInterface;
use App\Logic\IdentityAccess\LoginToken\LoginTokenProviderInterface;
use App\Logic\IdentityAccess\LoginToken\Model\LoginToken;

readonly class LoginTokenManager implements LoginTokenManagerInterface
{
    public function __construct(
        private LoginTokenProviderInterface $provider,
        private LoginTokenProcessorInterface $processor,
    ) {
    }

    public function findByHash(string $tokenHash): ?LoginToken
    {
        return $this->provider->findByHash($tokenHash);
    }

    public function save(LoginToken $token): LoginToken
    {
        return $this->processor->save($token);
    }

    public function markConsumed(string $id, \DateTimeImmutable $consumedAt): void
    {
        $this->processor->markConsumed($id, $consumedAt);
    }
}
