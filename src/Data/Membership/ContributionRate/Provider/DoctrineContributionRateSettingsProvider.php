<?php

namespace App\Data\Membership\ContributionRate\Provider;

use App\Data\Membership\ContributionRate\Entity\ContributionRateSettingsEntity;
use App\Data\Membership\ContributionRate\Mapper\ContributionRateSettingsMapper;
use App\Logic\Membership\ContributionRate\ContributionRateSettingsProviderInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionRateSettings;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineContributionRateSettingsProvider implements ContributionRateSettingsProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContributionRateSettingsMapper $mapper,
    ) {
    }

    public function get(): ContributionRateSettings
    {
        $entity = $this->entityManager->find(ContributionRateSettingsEntity::class, ContributionRateSettingsEntity::ID);

        // Defensiver Fallback: Die Migration legt die Singleton-Zeile bereits an; fehlt sie
        // dennoch (z. B. ein per SchemaTool aus den Mappings aufgebautes Test-Schema ohne
        // Migrationslauf), wird ein leerer Ausgangszustand geliefert, ohne dass Aufrufer den
        // „noch nicht angelegt“-Fall gesondert behandeln müssen.
        return $entity === null ? new ContributionRateSettings(validFrom: null) : $this->mapper->toModel($entity);
    }
}
