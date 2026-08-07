<?php

namespace App\Data\Guestbook\Entry\Processor;

use App\Data\Guestbook\Entry\Entity\GuestbookEntryEntity;
use App\Data\Guestbook\Entry\Mapper\GuestbookEntryMapper;
use App\Logic\Guestbook\Entry\Exception\GuestbookEntryNotFoundException;
use App\Logic\Guestbook\Entry\GuestbookEntryProcessorInterface;
use App\Logic\Guestbook\Entry\Model\GuestbookEntry;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineGuestbookEntryProcessor implements GuestbookEntryProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GuestbookEntryMapper $mapper,
    ) {
    }

    public function save(GuestbookEntry $entry): GuestbookEntry
    {
        $entity = $this->entityManager->find(GuestbookEntryEntity::class, $entry->id);
        if ($entity === null) {
            if ($entry->status !== \App\Logic\Guestbook\Entry\Model\GuestbookStatus::Pending) {
                throw new GuestbookEntryNotFoundException($entry->id);
            }
            $entity = $this->mapper->createEntity($entry);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($entry, $entity);
        }

        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }

    public function import(GuestbookEntry $entry): bool
    {
        if ($this->entityManager->find(GuestbookEntryEntity::class, $entry->id) !== null) {
            return false;
        }

        $this->entityManager->persist($this->mapper->createEntity($entry));
        $this->entityManager->flush();

        return true;
    }
}
