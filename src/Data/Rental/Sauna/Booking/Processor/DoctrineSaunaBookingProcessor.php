<?php

namespace App\Data\Rental\Sauna\Booking\Processor;

use App\Data\Rental\Sauna\Booking\Entity\SaunaBookingEntity;
use App\Data\Rental\Sauna\Booking\Mapper\SaunaBookingMapper;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\SaunaBookingProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineSaunaBookingProcessor implements SaunaBookingProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaunaBookingMapper $mapper,
    ) {
    }

    public function save(SaunaBooking $booking): SaunaBooking
    {
        $entity = $this->stage($booking);
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }

    public function saveAll(array $bookings): array
    {
        // Ein gemeinsamer flush() schreibt alle Buchungen in einer Transaktion — ganz oder gar nicht.
        $entities = array_map($this->stage(...), $bookings);
        $this->entityManager->flush();

        return array_map($this->mapper->toModel(...), $entities);
    }

    private function stage(SaunaBooking $booking): SaunaBookingEntity
    {
        $entity = $this->entityManager->find(SaunaBookingEntity::class, $booking->id);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($booking);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($booking, $entity);
        }

        return $entity;
    }
}
