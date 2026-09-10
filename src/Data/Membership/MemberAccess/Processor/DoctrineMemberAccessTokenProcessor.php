<?php

namespace App\Data\Membership\MemberAccess\Processor;

use App\Data\Membership\MemberAccess\Mapper\MemberAccessTokenMapper;
use App\Logic\Membership\MemberAccess\MemberAccessTokenProcessorInterface;
use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ein Token wird nie verändert, nur einmalig angelegt — es gibt bewusst keinen „update“-Zweig wie
 * bei den übrigen Prozessoren.
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
}
