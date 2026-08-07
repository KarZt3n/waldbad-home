<?php

namespace App\UI\Guestbook\Entry\Http;

use App\Logic\Guestbook\Entry\Query\ListGuestbookEntriesQuery;
use App\Logic\Guestbook\Entry\UseCase\ModerateGuestbookEntryUseCase;
use App\UI\IdentityAccess\Security\AuthenticatedUser;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/admin/v1/guestbook-entries')]
class AdminGuestbookController extends AbstractController
{
    public function __construct(private readonly GuestbookResponseFactory $responseFactory)
    {
    }

    #[Route('', name: 'api_admin_guestbook_list', methods: ['GET'])]
    public function list(ListGuestbookEntriesQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::GuestbookModerate->value);

        return new JsonResponse($this->responseFactory->collection($query->execute()));
    }

    #[Route('/{id}/{action}', name: 'api_admin_guestbook_moderate', requirements: ['action' => 'approve|reject|mark-spam'], methods: ['POST'])]
    public function moderate(
        string $id,
        string $action,
        ModerateGuestbookEntryUseCase $useCase,
        #[CurrentUser] AuthenticatedUser $user,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(Permission::GuestbookModerate->value);
        $entry = $useCase->execute($id, $action, $user->getId());

        return new JsonResponse($this->responseFactory->entry($entry));
    }
}
