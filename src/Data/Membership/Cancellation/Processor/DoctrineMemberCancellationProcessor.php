<?php

namespace App\Data\Membership\Cancellation\Processor;

use App\Data\Membership\Cancellation\Entity\MemberCancellationEntity;
use App\Data\Membership\Cancellation\Mapper\MemberCancellationMapper;
use App\Logic\Membership\Cancellation\Exception\MemberCancellationNotFoundException;
use App\Logic\Membership\Cancellation\MemberCancellationProcessorInterface;
use App\Logic\Membership\Cancellation\Model\MemberCancellation;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMemberCancellationProcessor implements MemberCancellationProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MemberCancellationMapper $mapper,
    ) {
    }

    public function save(MemberCancellation $cancellation): MemberCancellation
    {
        $entity = $this->entityManager->find(MemberCancellationEntity::class, $cancellation->id);
        if ($entity === null) {
            if (!$cancellation->isOpen()) {
                throw new MemberCancellationNotFoundException($cancellation->id);
            }
            $entity = $this->mapper->createEntity($cancellation);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($cancellation, $entity);
        }

        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
