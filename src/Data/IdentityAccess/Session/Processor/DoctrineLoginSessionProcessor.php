<?php

namespace App\Data\IdentityAccess\Session\Processor;

use App\Data\IdentityAccess\Session\Entity\LoginSessionEntity;
use App\Data\IdentityAccess\Session\Mapper\LoginSessionMapper;
use App\Logic\IdentityAccess\Session\LoginSessionProcessorInterface;
use App\Logic\IdentityAccess\Session\Model\LoginSession;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineLoginSessionProcessor implements LoginSessionProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoginSessionMapper $mapper,
    ) {
    }

    public function save(LoginSession $session): LoginSession
    {
        $entity = $this->entityManager->find(LoginSessionEntity::class, $session->id);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($session);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($session, $entity);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
