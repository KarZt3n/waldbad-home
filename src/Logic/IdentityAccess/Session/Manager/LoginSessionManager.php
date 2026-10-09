<?php

namespace App\Logic\IdentityAccess\Session\Manager;

use App\Logic\IdentityAccess\Session\LoginSessionProcessorInterface;
use App\Logic\IdentityAccess\Session\LoginSessionProviderInterface;
use App\Logic\IdentityAccess\Session\Model\LoginSession;

readonly class LoginSessionManager implements LoginSessionManagerInterface
{
    public function __construct(
        private LoginSessionProviderInterface $provider,
        private LoginSessionProcessorInterface $processor,
    ) {
    }

    public function find(string $id): ?LoginSession
    {
        return $this->provider->find($id);
    }

    public function findOpenForUser(string $userId): array
    {
        return $this->provider->findOpenForUser($userId);
    }

    public function findRecent(int $limit): array
    {
        return $this->provider->findRecent($limit);
    }

    public function save(LoginSession $session): LoginSession
    {
        return $this->processor->save($session);
    }
}
