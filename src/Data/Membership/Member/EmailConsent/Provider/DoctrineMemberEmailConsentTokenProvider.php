<?php

namespace App\Data\Membership\Member\EmailConsent\Provider;

use App\Data\Membership\Member\EmailConsent\Entity\MemberEmailConsentTokenEntity;
use App\Data\Membership\Member\EmailConsent\Mapper\MemberEmailConsentTokenMapper;
use App\Logic\Membership\Member\EmailConsent\MemberEmailConsentTokenProviderInterface;
use App\Logic\Membership\Member\EmailConsent\Model\MemberEmailConsentToken;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMemberEmailConsentTokenProvider implements MemberEmailConsentTokenProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MemberEmailConsentTokenMapper $mapper,
    ) {
    }

    public function findByHash(string $tokenHash): ?MemberEmailConsentToken
    {
        $entity = $this->entityManager->getRepository(MemberEmailConsentTokenEntity::class)->findOneBy(['tokenHash' => $tokenHash]);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }
}
