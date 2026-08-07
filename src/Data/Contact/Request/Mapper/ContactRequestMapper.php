<?php

namespace App\Data\Contact\Request\Mapper;

use App\Data\Contact\Request\Entity\ContactRequestEntity;
use App\Logic\Contact\Request\Model\ContactRequest;
use App\Logic\Contact\Request\Model\ContactRequestStatus;

readonly class ContactRequestMapper
{
    public function toModel(ContactRequestEntity $entity): ContactRequest
    {
        return new ContactRequest(
            id: $entity->getId(),
            name: $entity->getName(),
            email: $entity->getEmail(),
            subject: $entity->getSubject(),
            message: $entity->getMessage(),
            status: ContactRequestStatus::from($entity->getStatus()),
            submittedAt: $entity->getSubmittedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function createEntity(ContactRequest $request): ContactRequestEntity
    {
        return new ContactRequestEntity(
            id: $request->id,
            name: $request->name,
            email: $request->email,
            subject: $request->subject,
            message: $request->message,
            status: $request->status->value,
            submittedAt: $request->submittedAt,
            updatedAt: $request->updatedAt,
        );
    }

    public function updateEntity(ContactRequest $request, ContactRequestEntity $entity): void
    {
        $entity->changeStatus($request->status->value, $request->updatedAt);
    }
}
