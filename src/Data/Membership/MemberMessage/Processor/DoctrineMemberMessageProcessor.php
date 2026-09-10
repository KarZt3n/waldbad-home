<?php

namespace App\Data\Membership\MemberMessage\Processor;

use App\Data\Membership\MemberMessage\Entity\MemberMessageEntity;
use App\Data\Membership\MemberMessage\Mapper\MemberMessageMapper;
use App\Logic\Membership\MemberMessage\Exception\MemberMessageNotFoundException;
use App\Logic\Membership\MemberMessage\MemberMessageProcessorInterface;
use App\Logic\Membership\MemberMessage\Model\MemberMessage;
use App\Logic\Membership\MemberMessage\Model\MemberMessageStatus;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMemberMessageProcessor implements MemberMessageProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MemberMessageMapper $mapper,
    ) {
    }

    public function save(MemberMessage $message): MemberMessage
    {
        $entity = $this->entityManager->find(MemberMessageEntity::class, $message->id);
        if ($entity === null) {
            if ($message->status !== MemberMessageStatus::New) {
                throw new MemberMessageNotFoundException($message->id);
            }
            $entity = $this->mapper->createEntity($message);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($message, $entity);
        }

        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
