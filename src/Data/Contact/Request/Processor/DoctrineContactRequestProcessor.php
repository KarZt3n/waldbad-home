<?php

namespace App\Data\Contact\Request\Processor;

use App\Data\Contact\Request\Entity\ContactRequestEntity;
use App\Data\Contact\Request\Mapper\ContactRequestMapper;
use App\Logic\Contact\Request\ContactRequestProcessorInterface;
use App\Logic\Contact\Request\Exception\ContactRequestNotFoundException;
use App\Logic\Contact\Request\Model\ContactRequest;
use App\Logic\Contact\Request\Model\ContactRequestStatus;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineContactRequestProcessor implements ContactRequestProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContactRequestMapper $mapper,
    ) {
    }

    public function save(ContactRequest $request): ContactRequest
    {
        $entity = $this->entityManager->find(ContactRequestEntity::class, $request->id);
        if ($entity === null) {
            if ($request->status !== ContactRequestStatus::New) {
                throw new ContactRequestNotFoundException($request->id);
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
