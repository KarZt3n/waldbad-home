<?php

namespace App\Data\Event\HelpRequest\Provider;

use App\Data\Event\HelpRequest\Entity\EventHelpRequestEntity;
use App\Data\Event\HelpRequest\Mapper\EventHelpRequestMapper;
use App\Logic\Event\HelpRequest\EventHelpRequestProviderInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineEventHelpRequestProvider implements EventHelpRequestProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventHelpRequestMapper $mapper,
    ) {
    }

    public function find(string $id): ?EventHelpRequest
    {
        $entity = $this->entityManager->find(EventHelpRequestEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(EventHelpRequestEntity::class)->findBy([], ['submittedAt' => 'DESC']);

        return array_map($this->mapper->toModel(...), $entities);
    }
}
