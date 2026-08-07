<?php

namespace App\Logic\Contact\Request\Query;

use App\Logic\Contact\Request\Dto\ContactRequestResponse;
use App\Logic\Contact\Request\Manager\ContactRequestManagerInterface;

readonly class ListContactRequestsQuery
{
    public function __construct(private ContactRequestManagerInterface $manager)
    {
    }

    /**
     * @return list<ContactRequestResponse>
     */
    public function execute(): array
    {
        return array_map(ContactRequestResponse::fromRequest(...), $this->manager->all());
    }
}
