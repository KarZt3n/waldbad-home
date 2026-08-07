<?php

namespace App\Data\Contact\Request\Provider;

use App\Data\Contact\Request\Entity\ContactRequestEntity;
use App\Data\Contact\Request\Mapper\ContactRequestMapper;
use App\Logic\Contact\Request\ContactRequestProviderInterface;
use App\Logic\Contact\Request\Model\ContactRequest;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineContactRequestProvider implements ContactRequestProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContactRequestMapper $mapper,
    ) {
    }

    public function find(string $id): ?ContactRequest
    {
        $entity = $this->entityManager->find(ContactRequestEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(ContactRequestEntity::class)->findBy([], ['submittedAt' => 'DESC']);

        return array_map($this->mapper->toModel(...), $entities);
    }
}
