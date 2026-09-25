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
        $entity = $this->entityManager->find(SaunaBookingEntity::class, $booking->id);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($booking);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($booking, $entity);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
