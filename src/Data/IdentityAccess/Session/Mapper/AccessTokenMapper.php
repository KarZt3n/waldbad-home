<?php

namespace App\Data\IdentityAccess\Session\Mapper;

use App\Data\IdentityAccess\Session\Entity\AccessTokenEntity;
use App\Logic\IdentityAccess\Session\Model\AccessToken;

readonly class AccessTokenMapper
{
    public function toModel(AccessTokenEntity $entity): AccessToken
    {
        return new AccessToken(
            id: $entity->getId(),
            userId: $entity->getUserId(),
            tokenHash: $entity->getTokenHash(),
            expiresAt: $entity->getExpiresAt(),
            createdAt: $entity->getCreatedAt(),
        );
    }

    public function createEntity(AccessToken $token): AccessTokenEntity
    {
        return new AccessTokenEntity(
            id: $token->id,
            userId: $token->userId,
            tokenHash: $token->tokenHash,
            expiresAt: $token->expiresAt,
            createdAt: $token->createdAt,
        );
    }
}
