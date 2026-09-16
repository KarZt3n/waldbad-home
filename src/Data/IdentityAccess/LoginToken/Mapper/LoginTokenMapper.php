<?php

namespace App\Data\IdentityAccess\LoginToken\Mapper;

use App\Data\IdentityAccess\LoginToken\Entity\LoginTokenEntity;
use App\Logic\IdentityAccess\LoginToken\Model\LoginToken;

readonly class LoginTokenMapper
{
    public function toModel(LoginTokenEntity $entity): LoginToken
    {
        return new LoginToken(
            id: $entity->getId(),
            email: $entity->getEmail(),
            tokenHash: $entity->getTokenHash(),
            expiresAt: $entity->getExpiresAt(),
            consumedAt: $entity->getConsumedAt(),
        );
    }

    public function createEntity(LoginToken $token): LoginTokenEntity
    {
        return new LoginTokenEntity(
            id: $token->id,
            email: $token->email,
            tokenHash: $token->tokenHash,
            expiresAt: $token->expiresAt,
            consumedAt: $token->consumedAt,
        );
    }
}
