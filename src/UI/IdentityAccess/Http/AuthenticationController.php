<?php

namespace App\UI\IdentityAccess\Http;

use App\Logic\IdentityAccess\Authentication\Query\GetAuthenticationIdentityQuery;
use App\Logic\Common\Messaging\AsyncEventPublisherInterface;
use App\Logic\IdentityAccess\LoginToken\Event\LoginRequestedEvent;
use App\Logic\IdentityAccess\Session\Dto\AuthenticatedSession;
use App\Logic\IdentityAccess\Session\Dto\IssuedSession;
use App\Logic\IdentityAccess\Session\UseCase\LogoutUseCase;
use App\Logic\IdentityAccess\Session\UseCase\RedeemLoginTokenUseCase;
use App\Logic\IdentityAccess\Session\UseCase\RefreshSessionUseCase;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\PageAccess;
use App\Logic\IdentityAccess\User\Model\Role;
use App\UI\Common\RateLimit\RateLimitMessage;
use App\UI\IdentityAccess\Security\AccessTokenHandler;
use App\UI\IdentityAccess\Security\AdminCsrfSubscriber;
use App\UI\IdentityAccess\Security\AuthenticatedUser;
use App\UI\IdentityAccess\Security\CookieAccessTokenExtractor;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Passwortlose Anmeldung fürs Redaktions-Backend: E-Mail-Adresse → Anmeldelink per Mail
 * (`RequestLoginUseCase`) → Einlösen des Links stellt Access- und Refresh-Token aus
 * (`RedeemLoginTokenUseCase`, siehe `App\Logic\IdentityAccess\Session\Service\SessionTokenIssuer`
 * für die Lebensdauer-Regeln). Der Access-Token wird bei jedem Request über
 * `CookieAccessTokenExtractor`/`AccessTokenHandler` neu geprüft; `refresh()` rotiert das
 * Token-Paar, solange das Frontend aktiv ist (siehe `assets/app.js`).
 */
#[Route('/api/auth/v1')]
readonly class AuthenticationController
{
    private const string REFRESH_TOKEN_COOKIE = 'cms_refresh_token';
    private const string REFRESH_TOKEN_PATH = '/api/auth/v1';

    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
        private GetAuthenticationIdentityQuery $identityQuery,
        private RateLimiterFactory $loginRequestLimiter,
        private RateLimiterFactory $loginSessionLimiter,
        private AsyncEventPublisherInterface $eventPublisher,
    ) {
    }

    #[Route('/login-requests', name: 'api_login_request', methods: ['POST'])]
    public function requestLogin(Request $request): JsonResponse
    {
        $email = trim($request->getPayload()->getString('email'));
        if ($email === '') {
            throw new BadRequestHttpException('Die E-Mail-Adresse ist erforderlich.');
        }

        $limit = $this->loginRequestLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            $retryAfterSeconds = $limit->getRetryAfter()->getTimestamp() - time();
            throw new TooManyRequestsHttpException($retryAfterSeconds, RateLimitMessage::exceeded($retryAfterSeconds));
        }

        $this->eventPublisher->publish(new LoginRequestedEvent($email));

        return new JsonResponse([
            'message' => 'Falls diese E-Mail-Adresse hinterlegt ist, wird ein Anmeldelink verschickt.',
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, RedeemLoginTokenUseCase $useCase): JsonResponse
    {
        $token = trim($request->getPayload()->getString('token'));
        if ($token === '') {
            throw new BadRequestHttpException('Der Anmeldelink ist unvollständig.');
        }

        $limit = $this->loginSessionLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            $retryAfterSeconds = $limit->getRetryAfter()->getTimestamp() - time();
            throw new TooManyRequestsHttpException($retryAfterSeconds, RateLimitMessage::exceeded($retryAfterSeconds));
        }

        return $this->sessionResponse($request, $useCase->execute($token));
    }

    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request, RefreshSessionUseCase $useCase): JsonResponse
    {
        $rawRefreshToken = $request->cookies->get(self::REFRESH_TOKEN_COOKIE);
        if (!is_string($rawRefreshToken) || $rawRefreshToken === '') {
            return $this->unauthenticatedResponse();
        }

        return $this->sessionResponse($request, $useCase->execute($rawRefreshToken));
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request, LogoutUseCase $useCase): Response
    {
        $rawAccessToken = $request->cookies->get(CookieAccessTokenExtractor::COOKIE_NAME);
        $rawRefreshToken = $request->cookies->get(self::REFRESH_TOKEN_COOKIE);
        $useCase->execute(
            is_string($rawAccessToken) && $rawAccessToken !== '' ? $rawAccessToken : null,
            is_string($rawRefreshToken) && $rawRefreshToken !== '' ? $rawRefreshToken : null,
        );

        $response = new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        $response->headers->clearCookie(CookieAccessTokenExtractor::COOKIE_NAME, '/', null, $request->isSecure(), true, 'lax');
        $response->headers->clearCookie(self::REFRESH_TOKEN_COOKIE, self::REFRESH_TOKEN_PATH, null, $request->isSecure(), true, 'lax');

        return $response;
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?AuthenticatedUser $user): JsonResponse
    {
        if ($user === null) {
            return new JsonResponse(['user' => null], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'user' => $this->userPayload($user),
            'csrfToken' => $this->csrfTokenManager->getToken(AdminCsrfSubscriber::TOKEN_ID)->getValue(),
        ]);
    }

    private function sessionResponse(Request $request, AuthenticatedSession $session): JsonResponse
    {
        $identity = $this->identityQuery->execute($session->user->email);
        $user = new AuthenticatedUser($identity);

        $response = new JsonResponse([
            'user' => $this->userPayload($user),
            'csrfToken' => $this->csrfTokenManager->getToken(AdminCsrfSubscriber::TOKEN_ID)->getValue(),
        ]);
        $this->attachSessionCookies($response, $request, $session->tokens);

        return $response;
    }

    private function attachSessionCookies(JsonResponse $response, Request $request, IssuedSession $tokens): void
    {
        $response->headers->setCookie(Cookie::create(
            name: CookieAccessTokenExtractor::COOKIE_NAME,
            value: $tokens->rawAccessToken,
            expire: $tokens->accessTokenExpiresAt,
            path: '/',
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        ));
        $response->headers->setCookie(Cookie::create(
            name: self::REFRESH_TOKEN_COOKIE,
            value: $tokens->rawRefreshToken,
            expire: $tokens->refreshTokenExpiresAt,
            path: self::REFRESH_TOKEN_PATH,
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    private function unauthenticatedResponse(): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => 'authentication_failed',
                'message' => 'Die Sitzung ist abgelaufen. Bitte melde dich erneut an.',
            ],
        ], JsonResponse::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(AuthenticatedUser $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getUserIdentifier(),
            'displayName' => $user->getDisplayName(),
            'roles' => array_map(static fn (Role $role): string => $role->value, $user->getDomainRoles()),
            'moduleAccess' => $this->moduleAccess($user->getModuleAccess()),
            'pageAccess' => $this->pageAccess($user->getPageAccess()),
        ];
    }

    /**
     * @param list<ModuleAccess> $moduleAccess
     * @return array<string, string>
     */
    private function moduleAccess(array $moduleAccess): array
    {
        $result = [];
        foreach ($moduleAccess as $access) {
            $result[$access->module->value] = $access->role->value;
        }

        return $result;
    }

    /**
     * @param list<PageAccess>|null $pageAccess
     * @return array<string, string>|null
     */
    private function pageAccess(?array $pageAccess): ?array
    {
        if ($pageAccess === null) {
            return null;
        }

        $result = [];
        foreach ($pageAccess as $access) {
            $result[$access->pageId] = $access->role->value;
        }

        return $result;
    }
}
