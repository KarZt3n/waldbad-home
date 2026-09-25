<?php

namespace App\Data\Rental\Sauna\Booking\Provider;

use App\Data\Rental\Sauna\Booking\Entity\SaunaBookingEntity;
use App\Data\Rental\Sauna\Booking\Mapper\SaunaBookingMapper;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\SaunaBookingProviderInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineSaunaBookingProvider implements SaunaBookingProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaunaBookingMapper $mapper,
    ) {
    }

    public function find(string $id): ?SaunaBooking
    {
        $entity = $this->entityManager->find(SaunaBookingEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(SaunaBookingEntity::class)->findBy([], ['date' => 'ASC', 'startTime' => 'ASC']);

        return array_map($this->mapper->toModel(...), $entities);
    }

    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('booking')
            ->from(SaunaBookingEntity::class, 'booking')
            ->where('booking.date BETWEEN :from AND :to')
            ->setParameter('from', $from->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->setParameter('to', $to->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->orderBy('booking.date', 'ASC')
            ->addOrderBy('booking.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        $bookings = [];
        foreach (is_array($result) ? $result : [] as $item) {
            if ($item instanceof SaunaBookingEntity) {
                $bookings[] = $this->mapper->toModel($item);
            }
        }

        return $bookings;
    }
}
