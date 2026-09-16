<?php

namespace App\Data\IdentityAccess\Session\Processor;

use App\Data\IdentityAccess\Session\Entity\RefreshTokenEntity;
use App\Data\IdentityAccess\Session\Mapper\RefreshTokenMapper;
use App\Logic\IdentityAccess\Session\Model\RefreshToken;
use App\Logic\IdentityAccess\Session\RefreshTokenProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineRefreshTokenProcessor implements RefreshTokenProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RefreshTokenMapper $mapper,
    ) {
    }

    public function save(RefreshToken $token): RefreshToken
    {
        // Siehe DoctrineLoginTokenProcessor::save() — dieselbe Absicherung gegen eine
        // Hash-Kollision, die bei 256 Bit Entropie praktisch nur der deterministische
        // Test-Doppelgänger FixedSecureTokenGenerator auslösen kann.
        $existing = $this->entityManager->getRepository(RefreshTokenEntity::class)->findOneBy(['tokenHash' => $token->tokenHash]);
        if ($existing !== null) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }

        $entity = $this->mapper->createEntity($token);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }

    public function revoke(string $id, \DateTimeImmutable $revokedAt): void
    {
        $entity = $this->entityManager->find(RefreshTokenEntity::class, $id);
        if ($entity === null) {
            return;
        }

        $entity->revoke($revokedAt);
        $this->entityManager->flush();
    }

    public function revokeAllForUser(string $userId, \DateTimeImmutable $revokedAt): void
    {
        $entities = $this->entityManager->getRepository(RefreshTokenEntity::class)->findBy(['userId' => $userId]);
        foreach ($entities as $entity) {
            if ($entity->getRevokedAt() === null) {
                $entity->revoke($revokedAt);
            }
        }
        $this->entityManager->flush();
    }
}
