<?php

namespace App\Data\Event\HelpRequest\Processor;

use App\Data\Event\HelpRequest\Entity\EventHelpRequestEntity;
use App\Data\Event\HelpRequest\Mapper\EventHelpRequestMapper;
use App\Logic\Event\HelpRequest\EventHelpRequestProcessorInterface;
use App\Logic\Event\HelpRequest\Exception\EventHelpRequestNotFoundException;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineEventHelpRequestProcessor implements EventHelpRequestProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventHelpRequestMapper $mapper,
    ) {
    }

    public function save(EventHelpRequest $request): EventHelpRequest
    {
        $entity = $this->entityManager->find(EventHelpRequestEntity::class, $request->id);
        if ($entity === null) {
            if ($request->status !== EventHelpRequestStatus::New) {
                throw new EventHelpRequestNotFoundException($request->id);
            }
            $entity = $this->mapper->createEntity($request);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($request, $entity);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
