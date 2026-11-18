<?php

namespace App\UI\Membership\Cancellation\Http;

use App\Logic\Membership\Cancellation\Dto\MemberCancellationResponse;
use App\Logic\Membership\Cancellation\Query\ListMemberCancellationsQuery;
use App\Logic\Membership\Cancellation\UseCase\ConfirmMemberCancellationUseCase;
use App\Logic\Membership\Cancellation\UseCase\RejectMemberCancellationUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Reiter „Kündigungen“ der Mitgliederverwaltung: über „Meine Mitgliedschaft“ eingegangene
 * Kündigungen bestätigen oder verwerfen.
 */
#[Route('/api/admin/v1/member-cancellations')]
class AdminMemberCancellationController extends AbstractController
{
    #[Route('', name: 'api_admin_member_cancellations_list', methods: ['GET'])]
    public function list(ListMemberCancellationsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersView->value);

        return new JsonResponse(['items' => array_map($this->toArray(...), $query->execute())]);
    }

    /**
     * Bestätigt eine Kündigung als Ganzes: alle übergebenen Mitglieder-Kündigungen gemeinsam.
     */
    #[Route('/confirm', name: 'api_admin_member_cancellations_confirm', methods: ['POST'])]
    public function confirm(Request $request, ConfirmMemberCancellationUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);

        return new JsonResponse(['items' => array_map($this->toArray(...), $useCase->execute($this->ids($request)))]);
    }

    #[Route('/reject', name: 'api_admin_member_cancellations_reject', methods: ['POST'])]
    public function reject(Request $request, RejectMemberCancellationUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);

        return new JsonResponse(['items' => array_map($this->toArray(...), $useCase->execute($this->ids($request)))]);
    }

    /**
     * @return list<string>
     */
    private function ids(Request $request): array
    {
        $ids = $request->getPayload()->all('ids');
        $valid = array_values(array_filter($ids, static fn (mixed $id): bool => is_string($id) && trim($id) !== ''));
        if ($valid === [] || count($valid) !== count($ids)) {
            throw new BadRequestHttpException('Bitte die Kündigungen angeben.');
        }

        return array_map(static fn (string $id): string => trim($id), $valid);
    }

    /**
     * @return array<string, string|null>
     */
    private function toArray(MemberCancellationResponse $cancellation): array
    {
        return [
            'id' => $cancellation->id,
            'memberId' => $cancellation->memberId,
            'memberNumber' => $cancellation->memberNumber,
            'memberName' => $cancellation->memberName,
            'primaryMemberNumber' => $cancellation->primaryMemberNumber,
            'requestedByEmail' => $cancellation->requestedByEmail,
            'requestedByName' => $cancellation->requestedByName,
            'requestedByMemberNumber' => $cancellation->requestedByMemberNumber,
            'submittedAt' => $cancellation->submittedAt->format(\DateTimeInterface::ATOM),
            'exitDate' => $cancellation->exitDate->format('Y-m-d'),
            'successorMemberId' => $cancellation->successorMemberId,
            'successorName' => $cancellation->successorName,
            'contactRecipientMemberId' => $cancellation->contactRecipientMemberId,
            'contactRecipientName' => $cancellation->contactRecipientName,
            'status' => $cancellation->status->value,
            'decidedAt' => $cancellation->decidedAt?->format(\DateTimeInterface::ATOM),
            'warning' => $cancellation->warning,
        ];
    }
}
