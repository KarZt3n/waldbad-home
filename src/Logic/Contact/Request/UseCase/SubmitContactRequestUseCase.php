<?php

namespace App\Logic\Contact\Request\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Contact\Request\ContactRequestNotifierInterface;
use App\Logic\Contact\Request\Dto\ContactRequestResponse;
use App\Logic\Contact\Request\Manager\ContactRequestManagerInterface;
use App\Logic\Contact\Request\Model\ContactRequest;
use App\Logic\Contact\Request\Model\ContactRequestStatus;

readonly class SubmitContactRequestUseCase
{
    public function __construct(
        private ContactRequestManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private ContactRequestNotifierInterface $notifier,
    ) {
    }

    public function execute(string $name, string $email, ?string $subject, string $message): ContactRequestResponse
    {
        $now = $this->clock->now();
        $request = new ContactRequest(
            id: $this->identifierGenerator->generate(),
            name: trim($name),
            email: mb_strtolower(trim($email)),
            subject: $subject === null ? null : trim($subject),
            message: trim($message),
            status: ContactRequestStatus::New,
            submittedAt: $now,
            updatedAt: $now,
        );

        $saved = $this->manager->save($request);
        $this->notifier->contactRequestSubmitted($saved);

        return ContactRequestResponse::fromRequest($saved);
    }
}
