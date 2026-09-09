<?php

namespace App\Data\Settings\Email\Processor;

use App\Data\Settings\Email\Entity\EmailSettingsEntity;
use App\Data\Settings\Email\Mapper\EmailSettingsMapper;
use App\Logic\Common\ClockInterface;
use App\Logic\Settings\Email\EmailSettingsProcessorInterface;
use App\Logic\Settings\Email\Model\EmailSettings;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineEmailSettingsProcessor implements EmailSettingsProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailSettingsMapper $mapper,
        private ClockInterface $clock,
    ) {
    }

    public function save(EmailSettings $settings): EmailSettings
    {
        $entity = $this->entityManager->find(EmailSettingsEntity::class, EmailSettingsEntity::ID);
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
