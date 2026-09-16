<?php

namespace App\UI\IdentityAccess\Handlers\LoginToken;

use App\Logic\IdentityAccess\LoginToken\Event\LoginRequestedEvent;
use App\Logic\IdentityAccess\LoginToken\UseCase\RequestLoginUseCase;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class RequestLoginHandler
{
    public function __construct(private RequestLoginUseCase $useCase)
    {
    }

    public function __invoke(LoginRequestedEvent $event): void
    {
        $this->useCase->execute($event->email);
    }
}
