<?php

namespace App\Data\IdentityAccess\Session\Processor;

use App\Data\IdentityAccess\Session\Entity\AccessTokenEntity;
use App\Data\IdentityAccess\Session\Mapper\AccessTokenMapper;
use App\Logic\IdentityAccess\Session\AccessTokenProcessorInterface;
use App\Logic\IdentityAccess\Session\Model\AccessToken;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineAccessTokenProcessor implements AccessTokenProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccessTokenMapper $mapper,
    ) {
    }

    public function save(AccessToken $token): AccessToken
    {
        // Siehe DoctrineLoginTokenProcessor::save() — dieselbe Absicherung gegen eine
        // Hash-Kollision, die bei 256 Bit Entropie praktisch nur der deterministische
        // Test-Doppelgänger FixedSecureTokenGenerator auslösen kann.
        $existing = $this->entityManager->getRepository(AccessTokenEntity::class)->findOneBy(['tokenHash' => $token->tokenHash]);
        if ($existing !== null) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }

        $entity = $this->mapper->createEntity($token);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(AccessTokenEntity::class, $id);
        if ($entity === null) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
