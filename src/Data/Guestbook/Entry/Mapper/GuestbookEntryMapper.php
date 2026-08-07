<?php

namespace App\Data\Guestbook\Entry\Mapper;

use App\Data\Guestbook\Entry\Entity\GuestbookEntryEntity;
use App\Logic\Guestbook\Entry\Model\GuestbookEntry;
use App\Logic\Guestbook\Entry\Model\GuestbookStatus;

readonly class GuestbookEntryMapper
{
    public function toModel(GuestbookEntryEntity $entity): GuestbookEntry
    {
        return new GuestbookEntry(
            id: $entity->getId(),
            displayName: $entity->getDisplayName(),
            email: $entity->getEmail(),
            message: $entity->getMessage(),
            status: GuestbookStatus::from($entity->getStatus()),
            submittedAt: $entity->getSubmittedAt(),
            moderatedAt: $entity->getModeratedAt(),
            moderatedBy: $entity->getModeratedBy(),
        );
    }

    public function createEntity(GuestbookEntry $entry): GuestbookEntryEntity
    {
        return new GuestbookEntryEntity(
            id: $entry->id,
            displayName: $entry->displayName,
            email: $entry->email,
            message: $entry->message,
            status: $entry->status->value,
            submittedAt: $entry->submittedAt,
            moderatedAt: $entry->moderatedAt,
            moderatedBy: $entry->moderatedBy,
        );
    }

    public function updateEntity(GuestbookEntry $entry, GuestbookEntryEntity $entity): void
    {
        if ($entry->moderatedAt !== null && $entry->moderatedBy !== null) {
            $entity->moderate($entry->status->value, $entry->moderatedAt, $entry->moderatedBy);
        }
    }
}
