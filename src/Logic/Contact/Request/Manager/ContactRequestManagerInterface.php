<?php

namespace App\Logic\Contact\Request\Manager;

use App\Logic\Contact\Request\Model\ContactRequest;

interface ContactRequestManagerInterface
{
    public function get(string $id): ContactRequest;

    /**
     * @return list<ContactRequest>
     */
    public function all(): array;

    public function save(ContactRequest $request): ContactRequest;
}
