<?php

namespace App\Data\Membership\Member\Provider;

use App\Data\Membership\Member\Entity\MemberEntity;
use App\Data\Membership\Member\Mapper\MemberMapper;
use App\Logic\Membership\Member\MemberProviderInterface;
use App\Logic\Membership\Member\Model\Member;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMemberProvider implements MemberProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MemberMapper $mapper,
    ) {
    }

    public function find(string $id): ?Member
    {
        $entity = $this->entityManager->find(MemberEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findByMemberNumber(string $memberNumber): ?Member
    {
        $entity = $this->entityManager->getRepository(MemberEntity::class)->findOneBy(['memberNumber' => $memberNumber]);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findByPrimaryMemberNumber(string $primaryMemberNumber): array
    {
        $entities = $this->entityManager->getRepository(MemberEntity::class)->findBy(['primaryMemberNumber' => $primaryMemberNumber]);

        return array_map($this->mapper->toModel(...), $entities);
    }

    public function findByPayerMemberId(string $payerMemberId): array
    {
        $entities = $this->entityManager->getRepository(MemberEntity::class)->findBy(['payerMemberId' => $payerMemberId]);

        return array_map($this->mapper->toModel(...), $entities);
    }

    public function search(?string $term): array
    {
        // "member" ist ein reserviertes DQL-Schlüsselwort (MEMBER OF) und darf nicht als Alias
        // verwendet werden.
        $queryBuilder = $this->entityManager->getRepository(MemberEntity::class)->createQueryBuilder('m')
            ->orderBy('m.lastName', 'ASC')
            ->addOrderBy('m.firstName', 'ASC');

        $term = trim((string) $term);
        if ($term !== '') {
            $queryBuilder->andWhere(
                'm.memberNumber LIKE :term OR m.primaryMemberNumber LIKE :term '
                .'OR m.lastName LIKE :term OR m.firstName LIKE :term OR m.city LIKE :term',
            )->setParameter('term', '%'.$term.'%');
        }

        $result = $queryBuilder->getQuery()->getResult();
        $entities = [];
        foreach (is_array($result) ? $result : [] as $item) {
            if ($item instanceof MemberEntity) {
                $entities[] = $item;
            }
        }

        return array_map($this->mapper->toModel(...), $entities);
    }
}
