<?php

namespace App\Logic\Guestbook\Entry\Manager;

use App\Logic\Guestbook\Entry\Exception\GuestbookEntryNotFoundException;
use App\Logic\Guestbook\Entry\GuestbookEntryProcessorInterface;
use App\Logic\Guestbook\Entry\GuestbookEntryProviderInterface;
use App\Logic\Guestbook\Entry\Model\GuestbookEntry;

readonly class GuestbookManager implements GuestbookManagerInterface
{
    public function __construct(
        private GuestbookEntryProviderInterface $provider,
        private GuestbookEntryProcessorInterface $processor,
    ) {
    }

    public function get(string $id): GuestbookEntry
    {
        return $this->provider->find($id) ?? throw new GuestbookEntryNotFoundException($id);
    }

    public function published(int $limit, int $offset): array
    {
        return $this->provider->findPublished($limit, $offset);
    }

    public function countPublished(): int
    {
        return $this->provider->countPublished();
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function save(GuestbookEntry $entry): GuestbookEntry
    {
        return $this->processor->save($entry);
    }

    public function import(GuestbookEntry $entry): bool
    {
        return $this->processor->import($entry);
    }
}
