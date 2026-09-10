<?php

namespace App\UI\Membership\MemberMessage\Http;

use App\Logic\Membership\MemberMessage\Dto\MemberMessageResponse;
use App\Logic\Membership\MemberMessage\Model\MemberMessageStatus;
use App\Logic\Membership\MemberMessage\Query\ListMemberMessagesQuery;
use App\Logic\Membership\MemberMessage\UseCase\ChangeMemberMessageStatusUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/member-messages')]
class AdminMemberMessageController extends AbstractController
{
    #[Route('', name: 'api_admin_member_messages_list', methods: ['GET'])]
    public function list(ListMemberMessagesQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MemberMessagesView->value);

        return new JsonResponse(['items' => array_map($this->toArray(...), $query->execute())]);
    }

    #[Route('/{id}/status/{status}', name: 'api_admin_member_messages_status', requirements: ['status' => 'new|in_progress|resolved'], methods: ['POST'])]
    public function status(string $id, MemberMessageStatus $status, ChangeMemberMessageStatusUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MemberMessagesEdit->value);

        return new JsonResponse($this->toArray($useCase->execute($id, $status)));
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(MemberMessageResponse $message): array
    {
        return [
            'id' => $message->id,
            'memberId' => $message->memberId,
            'memberNumber' => $message->memberNumber,
            'memberName' => $message->memberName,
            'message' => $message->message,
            'status' => $message->status->value,
            'submittedAt' => $message->submittedAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $message->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
