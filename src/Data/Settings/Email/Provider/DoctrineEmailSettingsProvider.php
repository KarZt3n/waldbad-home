<?php

namespace App\Data\Settings\Email\Provider;

use App\Data\Settings\Email\Entity\EmailSettingsEntity;
use App\Data\Settings\Email\Mapper\EmailSettingsMapper;
use App\Logic\Settings\Email\EmailSettingsProviderInterface;
use App\Logic\Settings\Email\Model\EmailSettings;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineEmailSettingsProvider implements EmailSettingsProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailSettingsMapper $mapper,
    ) {
    }

    public function get(): EmailSettings
    {
        $entity = $this->entityManager->find(EmailSettingsEntity::class, EmailSettingsEntity::ID);

        // Defensiver Fallback: Die Migration legt die Singleton-Zeile bereits an; fehlt sie
        // dennoch (z. B. ein per SchemaTool aus den Mappings aufgebautes Test-Schema ohne
        // Migrationslauf), wird ein leerer Ausgangszustand geliefert, ohne dass Aufrufer den
        // „noch nicht angelegt“-Fall gesondert behandeln müssen.
        return $entity === null ? new EmailSettings(null, null, null, null, null, null, null, []) : $this->mapper->toModel($entity);
    }
}
