<?php

namespace App\UI\Membership\Application\Http;

use App\Logic\Membership\Application\Query\GetMembershipApplicationMemberMatchQuery;
use App\Logic\Membership\Application\Query\GetMembershipApplicationQuery;
use App\Logic\Membership\Application\Query\ListMembershipApplicationsQuery;
use App\Logic\Membership\Application\UseCase\MergeMembershipApplicationUseCase;
use App\Logic\Membership\Application\UseCase\RejectMembershipApplicationUseCase;
use App\Logic\Membership\Application\UseCase\ReleaseMembershipApplicationUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/membership-applications')]
class AdminMembershipApplicationController extends AbstractController
{
    public function __construct(
        private readonly MembershipApplicationResponseFactory $responseFactory,
        private readonly MembershipApplicationRequestMapper $requestMapper,
    ) {
    }

    #[Route('', name: 'api_admin_membership_application_list', methods: ['GET'])]
    public function list(ListMembershipApplicationsQuery $query, GetMembershipApplicationMemberMatchQuery $matchQuery): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembershipApplicationsView->value);

        $collection = $this->responseFactory->collection($applications = $query->execute());
        // Der Hinweis „Bereits Mitglied“ zeigt Mitgliederdaten — nur mit Leserecht auf die Mitglieder.
        if ($this->isGranted(Permission::MembersView->value)) {
            foreach ($applications as $index => $application) {
                $open = $application->releasedAt === null && $application->rejectedAt === null;
                $match = $open ? $matchQuery->execute($application->id) : null;
                $collection['items'][$index]['memberMatch'] = $match === null ? null : $this->responseFactory->memberMatch($match);
            }
        }

        return new JsonResponse($collection);
    }

    #[Route('/{id}', name: 'api_admin_membership_application_get', methods: ['GET'])]
    public function get(string $id, GetMembershipApplicationQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembershipApplicationsView->value);

        return new JsonResponse($this->responseFactory->application($query->execute($id)));
    }

    /**
     * Legt aus einem Mitgliedsantrag Mitglieder in der Mitgliederverwaltung an. Ein Antrag kann
     * nur einmal freigegeben werden.
     */
    #[Route('/{id}/release', name: 'api_admin_membership_application_release', methods: ['POST'])]
    public function release(string $id, ReleaseMembershipApplicationUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembershipApplicationsEdit->value);
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);

        return new JsonResponse($this->responseFactory->application($useCase->execute($id)));
    }

    /**
     * Übernimmt einen Antrag in die bestehende Mitgliedschaft der Personen, die schon Mitglied sind,
     * statt neue Mitglieder anzulegen (siehe `MergeMembershipApplicationUseCase`).
     */
    #[Route('/{id}/merge', name: 'api_admin_membership_application_merge', methods: ['POST'])]
    public function merge(string $id, Request $request, MergeMembershipApplicationUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembershipApplicationsEdit->value);
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        [$keepExisting, $keepExistingBank, $leaveMemberIds] = $this->requestMapper->mergeChoices($request);

        return new JsonResponse($this->responseFactory->application($useCase->execute($id, $keepExisting, $keepExistingBank, $leaveMemberIds)));
    }

    /**
     * Lehnt einen Mitgliedsantrag ab: die Person(en) werden nicht als Mitglied angelegt. Ein
     * bereits als Mitglied angelegter oder bereits abgelehnter Antrag kann nicht mehr abgelehnt
     * werden.
     */
    #[Route('/{id}/reject', name: 'api_admin_membership_application_reject', methods: ['POST'])]
    public function reject(string $id, Request $request, RejectMembershipApplicationUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembershipApplicationsEdit->value);

        return new JsonResponse($this->responseFactory->application($useCase->execute($id, $this->requestMapper->reject($request))));
    }
}
