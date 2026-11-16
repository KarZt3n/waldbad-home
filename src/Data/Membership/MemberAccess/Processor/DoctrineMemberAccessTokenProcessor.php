<?php

namespace App\Data\Membership\MemberAccess\Processor;

use App\Data\Membership\MemberAccess\Mapper\MemberAccessTokenMapper;
use App\Logic\Membership\MemberAccess\MemberAccessTokenProcessorInterface;
use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;
use App\Data\Membership\MemberAccess\Entity\MemberAccessTokenEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ein Token wird nie inhaltlich verändert, nur einmalig angelegt — es gibt bewusst keinen
 * „update“-Zweig wie bei den übrigen Prozessoren. Einzige Ausnahme: Nach einer Zahler-Übertragung
 * mit neuer Hauptnummer werden die Zugänge des Haushalts an die neue Hauptnummer gebunden.
 */
readonly class DoctrineMemberAccessTokenProcessor implements MemberAccessTokenProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MemberAccessTokenMapper $mapper,
    ) {
    }

    public function save(MemberAccessToken $token): MemberAccessToken
    {
        $entity = $this->mapper->createEntity($token);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }

    public function reassignHousehold(string $fromPrimaryMemberNumber, string $toPrimaryMemberNumber): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(MemberAccessTokenEntity::class, 'record')
            ->set('record.primaryMemberNumber', ':to')
            ->where('record.primaryMemberNumber = :from')
            ->setParameter('to', $toPrimaryMemberNumber)
            ->setParameter('from', $fromPrimaryMemberNumber)
            ->getQuery()
            ->execute();
    }
}
