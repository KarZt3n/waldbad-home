<?php

namespace App\UI\IdentityAccess\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[AsEventListener(event: 'kernel.request')]
readonly class AdminCsrfSubscriber
{
    public const string TOKEN_ID = 'cms-api';

    public function __construct(private CsrfTokenManagerInterface $tokenManager)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $protectedPath = str_starts_with($request->getPathInfo(), '/api/admin/')
            || $request->getPathInfo() === '/api/auth/v1/logout';
        if ($request->isMethodSafe() || !$protectedPath) {
            return;
        }

        $token = $request->headers->get('X-CSRF-Token');
        if ($token !== null && $this->tokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, $token))) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => [
                'code' => 'invalid_csrf_token',
                'message' => 'Die Sicherheitsprüfung ist fehlgeschlagen. Bitte lade die Redaktion neu.',
            ],
        ], JsonResponse::HTTP_FORBIDDEN));
    }
}
