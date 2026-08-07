<?php

namespace App\Data\Membership\Application\Provider;

use App\Data\Membership\Application\Entity\MembershipApplicationEntity;
use App\Data\Membership\Application\Mapper\MembershipApplicationMapper;
use App\Logic\Membership\Application\MembershipApplicationProviderInterface;
use App\Logic\Membership\Application\Model\ApplicationStatus;
use App\Logic\Membership\Application\Model\MembershipApplication;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMembershipApplicationProvider implements MembershipApplicationProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MembershipApplicationMapper $mapper,
    ) {
    }

    public function find(string $id): ?MembershipApplication
    {
        $entity = $this->entityManager->find(MembershipApplicationEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findByStatus(?ApplicationStatus $status = null): array
    {
        $criteria = $status === null ? [] : ['status' => $status->value];
        $entities = $this->entityManager->getRepository(MembershipApplicationEntity::class)->findBy(
            $criteria,
            ['submittedAt' => 'DESC'],
        );

        return array_map($this->mapper->toModel(...), $entities);
    }
}
