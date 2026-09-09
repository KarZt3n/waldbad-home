<?php

namespace App\Data\Settings\Pin\Processor;

use App\Data\Settings\Pin\Entity\PinSettingsEntity;
use App\Data\Settings\Pin\Mapper\PinSettingsMapper;
use App\Logic\Common\ClockInterface;
use App\Logic\Settings\Pin\Model\PinSettings;
use App\Logic\Settings\Pin\PinSettingsProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrinePinSettingsProcessor implements PinSettingsProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PinSettingsMapper $mapper,
        private ClockInterface $clock,
    ) {
    }

    public function save(PinSettings $settings): PinSettings
    {
        $entity = $this->entityManager->find(PinSettingsEntity::class, PinSettingsEntity::ID);
        $now = $this->clock->now();
        if ($entity === null) {
            $entity = $this->mapper->createEntity($settings, $now);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($settings, $entity, $now);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
