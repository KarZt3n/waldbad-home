<?php

namespace App\UI\Contact\Request\Http;

use App\Logic\Contact\Request\Dto\ContactRequestResponse;

readonly class ContactResponseFactory
{
    /**
     * @param list<ContactRequestResponse> $requests
     * @return array{items: list<array{
     *     id: string,
     *     name: string,
     *     email: string,
     *     subject: string|null,
     *     message: string,
     *     status: string,
     *     submittedAt: string,
     *     updatedAt: string
     * }>}
     */
    public function collection(array $requests): array
    {
        return ['items' => array_map($this->request(...), $requests)];
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     email: string,
     *     subject: string|null,
     *     message: string,
     *     status: string,
     *     submittedAt: string,
     *     updatedAt: string
     * }
     */
    public function request(ContactRequestResponse $request): array
    {
        return [
            'id' => $request->id,
            'name' => $request->name,
            'email' => $request->email,
            'subject' => $request->subject,
            'message' => $request->message,
            'status' => $request->status->value,
            'submittedAt' => $request->submittedAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $request->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
