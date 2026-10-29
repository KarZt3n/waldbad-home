<?php

namespace App\Data\Rental\Sauna\Duty\Provider;

use App\Data\Rental\Sauna\Duty\Entity\SaunaDutyWaiverEntity;
use App\Data\Rental\Sauna\Duty\Mapper\SaunaDutyWaiverMapper;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyWaiver;
use App\Logic\Rental\Sauna\Duty\SaunaDutyWaiverProviderInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineSaunaDutyWaiverProvider implements SaunaDutyWaiverProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaunaDutyWaiverMapper $mapper,
    ) {
    }

    public function find(string $id): ?SaunaDutyWaiver
    {
        $entity = $this->entityManager->find(SaunaDutyWaiverEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('waiver')
            ->from(SaunaDutyWaiverEntity::class, 'waiver')
            ->where('waiver.date BETWEEN :from AND :to')
            ->setParameter('from', $from->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->setParameter('to', $to->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->orderBy('waiver.date', 'ASC')
            ->getQuery()
            ->getResult();

        $waivers = [];
        foreach (is_array($result) ? $result : [] as $item) {
            if ($item instanceof SaunaDutyWaiverEntity) {
                $waivers[] = $this->mapper->toModel($item);
            }
        }

        return $waivers;
    }
}
