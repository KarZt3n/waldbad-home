<?php

namespace App\Logic\Contact\Request;

use App\Logic\Contact\Request\Model\ContactRequest;

interface ContactRequestProcessorInterface
{
    public function save(ContactRequest $request): ContactRequest;
}
