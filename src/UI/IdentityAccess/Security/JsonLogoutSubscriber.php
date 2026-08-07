<?php

namespace App\UI\IdentityAccess\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class)]
final class JsonLogoutSubscriber
{
    public function __invoke(LogoutEvent $event): void
    {
        if ($event->getRequest()->getPathInfo() === '/api/auth/v1/logout') {
            $event->setResponse(new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT));
        }
    }
}
