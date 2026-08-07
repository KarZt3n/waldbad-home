<?php

namespace App\UI\Membership\Application\Http;

use App\Logic\Membership\Application\Model\ApplicationStatus;
use App\Logic\Membership\Application\Query\GetMembershipApplicationQuery;
use App\Logic\Membership\Application\Query\ListMembershipApplicationsQuery;
use App\Logic\Membership\Application\UseCase\RetryMembershipApplicationUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/membership-applications')]
class AdminMembershipApplicationController extends AbstractController
{
    public function __construct(private readonly MembershipApplicationResponseFactory $responseFactory)
    {
    }

    #[Route('', name: 'api_admin_membership_application_list', methods: ['GET'])]
    public function list(Request $request, ListMembershipApplicationsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembershipManage->value);
        $statusValue = trim((string) $request->query->get('status', ''));
        try {
            $status = $statusValue === '' ? null : ApplicationStatus::from($statusValue);
        } catch (\ValueError) {
            throw new BadRequestHttpException('Der Mitgliedsantragsstatus ist ungültig.');
        }

        return new JsonResponse($this->responseFactory->collection($query->execute($status)));
    }

    #[Route('/{id}', name: 'api_admin_membership_application_get', methods: ['GET'])]
    public function get(string $id, GetMembershipApplicationQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembershipManage->value);

        return new JsonResponse($this->responseFactory->application($query->execute($id)));
    }

    #[Route('/{id}/retry', name: 'api_admin_membership_application_retry', methods: ['POST'])]
    public function retry(string $id, RetryMembershipApplicationUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembershipManage->value);

        return new JsonResponse($this->responseFactory->application($useCase->execute($id)));
    }
}
