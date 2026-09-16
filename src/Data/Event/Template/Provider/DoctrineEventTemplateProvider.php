<?php

namespace App\Data\Event\Template\Provider;

use App\Data\Event\Template\Entity\EventTemplateEntity;
use App\Data\Event\Template\Mapper\EventTemplateMapper;
use App\Logic\Event\Template\EventTemplateProviderInterface;
use App\Logic\Event\Template\Model\EventTemplate;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineEventTemplateProvider implements EventTemplateProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventTemplateMapper $mapper,
    ) {
    }

    public function find(string $id): ?EventTemplate
    {
        $entity = $this->entityManager->find(EventTemplateEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(EventTemplateEntity::class)->findBy([], ['kind' => 'ASC', 'title' => 'ASC']);

        return array_map($this->mapper->toModel(...), $entities);
    }
}
