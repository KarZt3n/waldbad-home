<?php

namespace App\UI\Contact\Request\Http;

use App\Logic\Contact\Request\Model\ContactRequestStatus;
use App\Logic\Contact\Request\Query\ListContactRequestsQuery;
use App\Logic\Contact\Request\UseCase\ChangeContactRequestStatusUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/contact-requests')]
class AdminContactController extends AbstractController
{
    public function __construct(private readonly ContactResponseFactory $responseFactory)
    {
    }

    #[Route('', name: 'api_admin_contact_list', methods: ['GET'])]
    public function list(ListContactRequestsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContactManage->value);

        return new JsonResponse($this->responseFactory->collection($query->execute()));
    }

    #[Route('/{id}/status/{status}', name: 'api_admin_contact_status', requirements: ['status' => 'new|in_progress|resolved'], methods: ['POST'])]
    public function status(string $id, ContactRequestStatus $status, ChangeContactRequestStatusUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContactManage->value);

        return new JsonResponse($this->responseFactory->request($useCase->execute($id, $status)));
    }
}
