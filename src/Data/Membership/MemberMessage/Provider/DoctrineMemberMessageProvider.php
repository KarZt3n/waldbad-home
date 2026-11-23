<?php

namespace App\Data\Membership\MemberMessage\Provider;

use App\Data\Membership\MemberMessage\Entity\MemberMessageEntity;
use App\Data\Membership\MemberMessage\Mapper\MemberMessageMapper;
use App\Logic\Membership\MemberMessage\MemberMessageProviderInterface;
use App\Logic\Membership\MemberMessage\Model\MemberMessage;
use App\Logic\Membership\MemberMessage\Model\MemberMessageStatus;
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

    public function countOpen(): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(message.id)')
            ->from(MemberMessageEntity::class, 'message')
            ->where('message.status <> :resolved')
            ->setParameter('resolved', MemberMessageStatus::Resolved->value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
