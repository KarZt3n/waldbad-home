<?php

namespace App\UI\Membership\Dashboard\Http;

use App\Logic\Membership\Dashboard\Dto\MembershipDashboardResponse;
use App\Logic\Membership\Dashboard\Query\GetMembershipDashboardQuery;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/membership-dashboard')]
class AdminMembershipDashboardController extends AbstractController
{
    #[Route('', name: 'api_admin_membership_dashboard', methods: ['GET'])]
    public function get(GetMembershipDashboardQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersView->value);

        return new JsonResponse($this->toArray($query->execute()));
    }

    /**
     * @return array<string, int>
     */
    private function toArray(MembershipDashboardResponse $response): array
    {
        return [
            'totalMembers' => $response->totalMembers,
            'activeMembers' => $response->activeMembers,
            'pendingApplications' => $response->pendingApplications,
            'totalContributionCents' => $response->totalContributionCents,
            'leavingAtYearEnd' => $response->leavingAtYearEnd,
            'leftLastYearEnd' => $response->leftLastYearEnd,
        ];
    }
}
