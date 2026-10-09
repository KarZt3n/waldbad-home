<?php

namespace App\Data\Rental\Sauna\Duty\Provider;

use App\Data\Rental\Sauna\Duty\Entity\SaunaDutyEntity;
use App\Data\Rental\Sauna\Duty\Mapper\SaunaDutyMapper;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDuty;
use App\Logic\Rental\Sauna\Duty\SaunaDutyProviderInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineSaunaDutyProvider implements SaunaDutyProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaunaDutyMapper $mapper,
    ) {
    }

    public function find(string $id): ?SaunaDuty
    {
        $entity = $this->entityManager->find(SaunaDutyEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('duty')
            ->from(SaunaDutyEntity::class, 'duty')
            ->where('duty.date BETWEEN :from AND :to')
            ->setParameter('from', $from->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->setParameter('to', $to->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->orderBy('duty.date', 'ASC')
            ->addOrderBy('duty.time', 'ASC')
            ->addOrderBy('duty.lastName', 'ASC')
            ->getQuery()
            ->getResult();

        $duties = [];
        foreach (is_array($result) ? $result : [] as $item) {
            if ($item instanceof SaunaDutyEntity) {
                $duties[] = $this->mapper->toModel($item);
            }
        }

        return $duties;
    }
}
