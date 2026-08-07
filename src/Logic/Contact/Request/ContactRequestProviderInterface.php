<?php

namespace App\Logic\Contact\Request;

use App\Logic\Contact\Request\Model\ContactRequest;

interface ContactRequestProviderInterface
{
    public function find(string $id): ?ContactRequest;

    /**
     * @return list<ContactRequest>
     */
    public function findAll(): array;
}
