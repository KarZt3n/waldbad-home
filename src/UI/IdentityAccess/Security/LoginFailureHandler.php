<?php

namespace App\UI\IdentityAccess\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

readonly class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse([
            'error' => [
                'code' => 'authentication_failed',
                'message' => 'E-Mail-Adresse oder Passwort ist ungültig.',
            ],
        ], JsonResponse::HTTP_UNAUTHORIZED);
    }
}
