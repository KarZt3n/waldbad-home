<?php

namespace App\Data\Settings\MailTemplate\Provider;

use App\Data\Settings\MailTemplate\Entity\MailTemplateEntity;
use App\Data\Settings\MailTemplate\Mapper\MailTemplateMapper;
use App\Logic\Settings\MailTemplate\MailTemplateProviderInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMailTemplateProvider implements MailTemplateProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailTemplateMapper $mapper,
    ) {
    }

    public function find(MailTemplateKey $key): ?MailTemplate
    {
        $entity = $this->entityManager->find(MailTemplateEntity::class, $key->value);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(MailTemplateEntity::class)->findAll();

        return array_map($this->mapper->toModel(...), $entities);
    }
}
