<?php

namespace App\UI\Guestbook\Entry\Http;

use App\Logic\Guestbook\Entry\Dto\GuestbookEntryResponse;

readonly class GuestbookResponseFactory
{
    /**
     * @param list<GuestbookEntryResponse> $entries
     * @return array{items: list<array{
     *     id: string,
     *     displayName: string,
     *     email: string|null,
     *     message: string,
     *     status: string,
     *     submittedAt: string,
     *     moderatedAt: string|null,
     *     moderatedBy: string|null
     * }>}
     */
    public function collection(array $entries): array
    {
        return ['items' => array_map($this->entry(...), $entries)];
    }

    /**
     * @param list<GuestbookEntryResponse> $entries
     * @return array{items: list<array{id: string, displayName: string, message: string, submittedAt: string}>, total: int}
     */
    public function publicCollection(array $entries, int $total): array
    {
        return [
            'items' => array_map(
                static fn (GuestbookEntryResponse $entry): array => [
                    'id' => $entry->id,
                    'displayName' => $entry->displayName,
                    'message' => $entry->message,
                    'submittedAt' => $entry->submittedAt->format(\DateTimeInterface::ATOM),
                ],
                $entries,
            ),
            'total' => $total,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     displayName: string,
     *     email: string|null,
     *     message: string,
     *     status: string,
     *     submittedAt: string,
     *     moderatedAt: string|null,
     *     moderatedBy: string|null
     * }
     */
    public function entry(GuestbookEntryResponse $entry): array
    {
        return [
            'id' => $entry->id,
            'displayName' => $entry->displayName,
            'email' => $entry->email,
            'message' => $entry->message,
            'status' => $entry->status->value,
            'submittedAt' => $entry->submittedAt->format(\DateTimeInterface::ATOM),
            'moderatedAt' => $entry->moderatedAt?->format(\DateTimeInterface::ATOM),
            'moderatedBy' => $entry->moderatedBy,
        ];
    }
}
