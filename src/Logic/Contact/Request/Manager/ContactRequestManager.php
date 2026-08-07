<?php

namespace App\Logic\Contact\Request\Manager;

use App\Logic\Contact\Request\ContactRequestProcessorInterface;
use App\Logic\Contact\Request\ContactRequestProviderInterface;
use App\Logic\Contact\Request\Exception\ContactRequestNotFoundException;
use App\Logic\Contact\Request\Model\ContactRequest;

readonly class ContactRequestManager implements ContactRequestManagerInterface
{
    public function __construct(
        private ContactRequestProviderInterface $provider,
        private ContactRequestProcessorInterface $processor,
    ) {
    }

    public function get(string $id): ContactRequest
    {
        return $this->provider->find($id) ?? throw new ContactRequestNotFoundException($id);
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function save(ContactRequest $request): ContactRequest
    {
        return $this->processor->save($request);
    }
}
