<?php

namespace App\UI\IdentityAccess\Security;

use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

readonly class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        if (!$user instanceof AuthenticatedUser) {
            return new JsonResponse(['error' => ['code' => 'authentication_failed', 'message' => 'Anmeldung fehlgeschlagen.']], 401);
        }

        return new JsonResponse([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getUserIdentifier(),
                'displayName' => $user->getDisplayName(),
                'roles' => array_map(static fn (Role $role): string => $role->value, $user->getDomainRoles()),
                'moduleAccess' => $this->moduleAccess($user->getModuleAccess()),
            ],
            'csrfToken' => $this->csrfTokenManager->getToken(AdminCsrfSubscriber::TOKEN_ID)->getValue(),
        ]);
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
}
