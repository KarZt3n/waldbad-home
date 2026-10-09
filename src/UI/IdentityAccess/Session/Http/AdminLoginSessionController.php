<?php

namespace App\UI\IdentityAccess\Session\Http;

use App\Logic\IdentityAccess\Session\Dto\LoginSessionResponse;
use App\Logic\IdentityAccess\Session\Query\ListLoginSessionsQuery;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/login-sessions')]
class AdminLoginSessionController extends AbstractController
{
    #[Route('', name: 'api_admin_login_sessions_list', methods: ['GET'])]
    public function list(ListLoginSessionsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::UserManagementView->value);

        $items = array_map(
            static fn (LoginSessionResponse $session): array => [
                'id' => $session->id,
                'userId' => $session->userId,
                'userDisplayName' => $session->userDisplayName,
                'userEmail' => $session->userEmail,
                'startedAt' => $session->startedAt->format(\DateTimeInterface::ATOM),
                'lastActivityAt' => $session->lastActivityAt->format(\DateTimeInterface::ATOM),
                'endedAt' => $session->endedAt?->format(\DateTimeInterface::ATOM),
                'endReason' => $session->endReason?->value,
                'userAgent' => $session->userAgent,
            ],
            $query->execute(),
        );

        return new JsonResponse(['items' => $items, 'total' => count($items)]);
    }
}
