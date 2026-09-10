<?php

namespace App\Logic\Membership\MemberAccess\Manager;

use App\Logic\Membership\MemberAccess\MemberAccessTokenProcessorInterface;
use App\Logic\Membership\MemberAccess\MemberAccessTokenProviderInterface;
use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;

readonly class MemberAccessTokenManager implements MemberAccessTokenManagerInterface
{
    public function __construct(
        private MemberAccessTokenProviderInterface $provider,
        private MemberAccessTokenProcessorInterface $processor,
    ) {
    }

    public function findByHash(string $tokenHash): ?MemberAccessToken
    {
        return $this->provider->findByHash($tokenHash);
    }

    public function save(MemberAccessToken $token): MemberAccessToken
    {
        return $this->processor->save($token);
    }
}
