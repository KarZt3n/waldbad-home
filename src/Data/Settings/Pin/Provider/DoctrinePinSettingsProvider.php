<?php

namespace App\Data\Settings\Pin\Provider;

use App\Data\Settings\Pin\Entity\PinSettingsEntity;
use App\Data\Settings\Pin\Mapper\PinSettingsMapper;
use App\Logic\Settings\Pin\Model\PinSettings;
use App\Logic\Settings\Pin\PinSettingsProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrinePinSettingsProvider implements PinSettingsProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PinSettingsMapper $mapper,
    ) {
    }

    public function get(): PinSettings
    {
        $entity = $this->entityManager->find(PinSettingsEntity::class, PinSettingsEntity::ID);

        // Defensiver Fallback: Die Migration legt die Singleton-Zeile bereits an; fehlt sie
        // dennoch (z. B. ein per SchemaTool aus den Mappings aufgebautes Test-Schema ohne
        // Migrationslauf), wird ein leerer Ausgangszustand geliefert, ohne dass Aufrufer den
        // „noch nicht angelegt“-Fall gesondert behandeln müssen.
        return $entity === null ? new PinSettings(null, [], []) : $this->mapper->toModel($entity);
    }
}
