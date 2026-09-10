<?php

namespace App\Data\Membership\MemberMessage\Provider;

use App\Data\Membership\MemberMessage\Entity\MemberMessageEntity;
use App\Data\Membership\MemberMessage\Mapper\MemberMessageMapper;
use App\Logic\Membership\MemberMessage\MemberMessageProviderInterface;
use App\Logic\Membership\MemberMessage\Model\MemberMessage;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMemberMessageProvider implements MemberMessageProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MemberMessageMapper $mapper,
    ) {
    }

    public function find(string $id): ?MemberMessage
    {
        $entity = $this->entityManager->find(MemberMessageEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(MemberMessageEntity::class)->findBy([], ['submittedAt' => 'DESC']);

        return array_map($this->mapper->toModel(...), $entities);
    }
}
