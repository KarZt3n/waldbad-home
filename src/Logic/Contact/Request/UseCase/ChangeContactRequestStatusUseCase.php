<?php

namespace App\Logic\Contact\Request\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Contact\Request\Dto\ContactRequestResponse;
use App\Logic\Contact\Request\Manager\ContactRequestManagerInterface;
use App\Logic\Contact\Request\Model\ContactRequestStatus;

readonly class ChangeContactRequestStatusUseCase
{
    public function __construct(
        private ContactRequestManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id, ContactRequestStatus $status): ContactRequestResponse
    {
        $request = $this->manager->get($id)->changeStatus($status, $this->clock->now());

        return ContactRequestResponse::fromRequest($this->manager->save($request));
    }
}
