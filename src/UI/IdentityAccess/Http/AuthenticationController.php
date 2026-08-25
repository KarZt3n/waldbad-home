<?php

namespace App\UI\IdentityAccess\Http;

use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\UI\IdentityAccess\Security\AuthenticatedUser;
use App\UI\IdentityAccess\Security\AdminCsrfSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/api/auth/v1')]
class AuthenticationController extends AbstractController
{
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Die Route wird von der Security-Firewall verarbeitet.');
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Die Route wird von der Security-Firewall verarbeitet.');
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?AuthenticatedUser $user, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        if ($user === null) {
            return new JsonResponse(['user' => null], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getUserIdentifier(),
                'displayName' => $user->getDisplayName(),
                'roles' => array_map(static fn (Role $role): string => $role->value, $user->getDomainRoles()),
                'moduleAccess' => $this->moduleAccess($user->getModuleAccess()),
            ],
            'csrfToken' => $csrfTokenManager->getToken(AdminCsrfSubscriber::TOKEN_ID)->getValue(),
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
