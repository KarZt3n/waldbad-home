<?php

namespace App\Data\IdentityAccess\LoginToken\Processor;

use App\Data\IdentityAccess\LoginToken\Entity\LoginTokenEntity;
use App\Data\IdentityAccess\LoginToken\Mapper\LoginTokenMapper;
use App\Logic\IdentityAccess\LoginToken\LoginTokenProcessorInterface;
use App\Logic\IdentityAccess\LoginToken\Model\LoginToken;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineLoginTokenProcessor implements LoginTokenProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoginTokenMapper $mapper,
    ) {
    }

    public function save(LoginToken $token): LoginToken
    {
        // Ein Hash-Kollision zweier unabhängig erzeugter Tokens ist bei 256 Bit Entropie praktisch
        // ausgeschlossen (siehe SecureTokenGeneratorInterface) — der einzige real vorkommende Fall
        // ist der deterministische Test-Doppelgänger FixedSecureTokenGenerator, wenn ein Test
        // mehrfach hintereinander einen Anmeldelink anfordert. Statt dort mit einem
        // Unique-Constraint-Fehler abzubrechen, wird die bestehende Zeile einfach ersetzt.
        $existing = $this->entityManager->getRepository(LoginTokenEntity::class)->findOneBy(['tokenHash' => $token->tokenHash]);
        if ($existing !== null) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }

        $entity = $this->mapper->createEntity($token);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }

    public function markConsumed(string $id, \DateTimeImmutable $consumedAt): void
    {
        $entity = $this->entityManager->find(LoginTokenEntity::class, $id);
        if ($entity === null) {
            return;
        }

        $entity->markConsumed($consumedAt);
        $this->entityManager->flush();
    }
}
