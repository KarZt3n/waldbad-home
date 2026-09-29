<?php

namespace App\UI\Membership\Dashboard\Http;

use App\Logic\Membership\Dashboard\Dto\ContributionRateCount;
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
     * @return array<string, int|list<array{label: string, count: int}>>
     */
    private function toArray(MembershipDashboardResponse $response): array
    {
        return [
            'totalMembers' => $response->totalMembers,
            'pendingApplications' => $response->pendingApplications,
            'totalContributionCents' => $response->totalContributionCents,
            'leavingAtYearEnd' => $response->leavingAtYearEnd,
            'leftLastYearEnd' => $response->leftLastYearEnd,
            'families' => $response->families,
            'individualMemberships' => $response->individualMemberships,
            'adults' => $response->adults,
            'minors' => $response->minors,
            'contributionRateCounts' => array_map(
                static fn (ContributionRateCount $rate): array => ['label' => $rate->label, 'count' => $rate->count],
                $response->contributionRateCounts,
            ),
        ];
    }
}
