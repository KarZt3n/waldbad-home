<?php

namespace App\Data\Membership\Member\EmailConsent\Processor;

use App\Data\Membership\Member\EmailConsent\Entity\MemberEmailConsentTokenEntity;
use App\Data\Membership\Member\EmailConsent\Mapper\MemberEmailConsentTokenMapper;
use App\Logic\Membership\Member\EmailConsent\MemberEmailConsentTokenProcessorInterface;
use App\Logic\Membership\Member\EmailConsent\Model\MemberEmailConsentToken;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMemberEmailConsentTokenProcessor implements MemberEmailConsentTokenProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MemberEmailConsentTokenMapper $mapper,
    ) {
    }

    public function save(MemberEmailConsentToken $token): MemberEmailConsentToken
    {
        // Ein Hash-Kollision zweier unabhängig erzeugter Tokens ist bei 256 Bit Entropie praktisch
        // ausgeschlossen (siehe SecureTokenGeneratorInterface) — der einzige real vorkommende Fall
        // ist der deterministische Test-Doppelgänger FixedSecureTokenGenerator (vgl.
        // DoctrineLoginTokenProcessor). Statt dort mit einem Unique-Constraint-Fehler abzubrechen,
        // wird die bestehende Zeile einfach ersetzt.
        $existing = $this->entityManager->getRepository(MemberEmailConsentTokenEntity::class)->findOneBy(['tokenHash' => $token->tokenHash]);
        if ($existing !== null) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }

        $entity = $this->mapper->createEntity($token);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }

    public function markConfirmed(string $id, \DateTimeImmutable $confirmedAt): void
    {
        $entity = $this->entityManager->find(MemberEmailConsentTokenEntity::class, $id);
        if ($entity === null) {
            return;
        }

        $entity->markConfirmed($confirmedAt);
        $this->entityManager->flush();
    }
}
