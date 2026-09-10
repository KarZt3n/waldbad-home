<?php

namespace App\Data\Membership\ContributionRate\Processor;

use App\Data\Membership\ContributionRate\Entity\ContributionRateSettingsEntity;
use App\Data\Membership\ContributionRate\Mapper\ContributionRateSettingsMapper;
use App\Logic\Membership\ContributionRate\ContributionRateSettingsProcessorInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionRateSettings;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineContributionRateSettingsProcessor implements ContributionRateSettingsProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContributionRateSettingsMapper $mapper,
    ) {
    }

    public function save(ContributionRateSettings $settings): ContributionRateSettings
    {
        $entity = $this->entityManager->find(ContributionRateSettingsEntity::class, ContributionRateSettingsEntity::ID);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($settings);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($settings, $entity);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
