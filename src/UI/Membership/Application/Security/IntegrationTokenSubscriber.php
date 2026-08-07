<?php

namespace App\UI\Membership\Application\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(event: 'kernel.request', priority: 100)]
readonly class IntegrationTokenSubscriber
{
    public function __construct(private string $membershipIntegrationToken)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/integration/v1/membership-applications')) {
            return;
        }
        if ($this->membershipIntegrationToken === '') {
            $event->setResponse($this->error('integration_disabled', 'Die Mitgliedschaftsschnittstelle ist nicht konfiguriert.', JsonResponse::HTTP_SERVICE_UNAVAILABLE));

            return;
        }
        $authorization = $request->headers->get('Authorization', '');
        $providedToken = str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : '';
        if ($providedToken === '' || !hash_equals($this->membershipIntegrationToken, $providedToken)) {
            $event->setResponse($this->error('invalid_integration_token', 'Der Zugriff auf die Mitgliedschaftsschnittstelle wurde abgelehnt.', JsonResponse::HTTP_UNAUTHORIZED));
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
