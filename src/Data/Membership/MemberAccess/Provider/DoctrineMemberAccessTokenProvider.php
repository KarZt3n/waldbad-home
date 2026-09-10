<?php

namespace App\Data\Membership\MemberAccess\Provider;

use App\Data\Membership\MemberAccess\Entity\MemberAccessTokenEntity;
use App\Data\Membership\MemberAccess\Mapper\MemberAccessTokenMapper;
use App\Logic\Membership\MemberAccess\MemberAccessTokenProviderInterface;
use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMemberAccessTokenProvider implements MemberAccessTokenProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MemberAccessTokenMapper $mapper,
    ) {
    }

    public function findByHash(string $tokenHash): ?MemberAccessToken
    {
        $entity = $this->entityManager->getRepository(MemberAccessTokenEntity::class)->findOneBy(['tokenHash' => $tokenHash]);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }
}
