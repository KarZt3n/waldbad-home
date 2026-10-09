<?php

namespace App\UI\Membership\WorkAssignment\Http;

use App\Logic\Membership\WorkAssignment\Dto\MemberWorkAssignmentYearResponse;
use App\Logic\Membership\WorkAssignment\Model\WorkAssignmentInterval;
use App\Logic\Membership\WorkAssignment\Model\WorkAssignmentParticipation;
use App\Logic\Membership\WorkAssignment\Query\GetMemberWorkAssignmentsQuery;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/members')]
class AdminMemberWorkAssignmentController extends AbstractController
{
    #[Route('/{id}/work-assignments', name: 'api_admin_member_work_assignments', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function list(string $id, GetMemberWorkAssignmentsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersView->value);

        return new JsonResponse(['years' => array_map(
            static fn (MemberWorkAssignmentYearResponse $year): array => [
                'year' => $year->year,
                'credit' => [
                    'liableMemberCount' => $year->credit->liableMemberCount,
                    'totalSurchargeCents' => $year->credit->totalSurchargeCents,
                    'requiredHoursPerAssignment' => $year->credit->requiredHoursPerAssignment,
                    'creditPerHourCents' => $year->credit->creditPerHourCents,
                    'workedMinutes' => $year->credit->workedMinutes,
                    'creditCents' => $year->credit->creditCents,
                ],
                'participations' => array_map(
                    static fn (WorkAssignmentParticipation $participation): array => [
                        'memberId' => $participation->memberId,
                        'memberName' => $year->memberNames[$participation->memberId] ?? null,
                        'eventTitle' => $participation->eventTitle,
                        'eventDate' => $participation->eventDate,
                        'eventTime' => $participation->eventTime,
                        'minutes' => $participation->minutes,
                        'intervals' => array_map(
                            static fn (WorkAssignmentInterval $interval): array => ['fromTime' => $interval->fromTime, 'toTime' => $interval->toTime],
                            $participation->intervals,
                        ),
                    ],
                    $year->participations,
                ),
            ],
            $query->execute($id),
        )]);
    }
}
