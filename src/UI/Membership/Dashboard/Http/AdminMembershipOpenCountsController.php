<?php

namespace App\UI\Membership\Dashboard\Http;

use App\Logic\Membership\Application\Query\CountOpenMembershipApplicationsQuery;
use App\Logic\Membership\Cancellation\Query\CountOpenMemberCancellationsQuery;
use App\Logic\Membership\MemberMessage\Query\CountOpenMemberMessagesQuery;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Zahlen offener Vorgänge für die Reiter der Mitgliederverwaltung — eine Anfrage mit drei
 * COUNT-Abfragen statt der vollständigen Listen. Bereiche ohne Leserecht liefern `null`.
 */
class AdminMembershipOpenCountsController extends AbstractController
{
    #[Route('/api/admin/v1/membership-open-counts', name: 'api_admin_membership_open_counts', methods: ['GET'])]
    public function counts(
        CountOpenMembershipApplicationsQuery $applications,
        CountOpenMemberMessagesQuery $messages,
        CountOpenMemberCancellationsQuery $cancellations,
    ): JsonResponse {
        $canViewApplications = $this->isGranted(Permission::MembershipApplicationsView->value);
        $canViewMessages = $this->isGranted(Permission::MemberMessagesView->value);
        $canViewMembers = $this->isGranted(Permission::MembersView->value);
        if (!$canViewApplications && !$canViewMessages && !$canViewMembers) {
            throw new AccessDeniedException();
        }

        return new JsonResponse([
            'applications' => $canViewApplications ? $applications->execute() : null,
            'messages' => $canViewMessages ? $messages->execute() : null,
            'cancellations' => $canViewMembers ? $cancellations->execute() : null,
        ]);
    }
}
