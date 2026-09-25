<?php

namespace App\UI\Membership\MemberMessage\Http;

use App\Logic\Membership\MemberMessage\Dto\MemberMessageResponse;
use App\Logic\Membership\MemberMessage\Dto\ReplyToMemberMessageRequest;
use App\Logic\Membership\MemberMessage\Model\MemberMessageStatus;
use App\Logic\Membership\MemberMessage\Query\ListMemberMessagesQuery;
use App\Logic\Membership\MemberMessage\UseCase\ChangeMemberMessageStatusUseCase;
use App\Logic\Membership\MemberMessage\UseCase\ReplyToMemberMessageUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

    #[Route('/{id}/reply', name: 'api_admin_member_messages_reply', methods: ['POST'])]
    public function reply(string $id, Request $request, ReplyToMemberMessageUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MemberMessagesEdit->value);
        $data = $request->getPayload();
        $recipient = trim($data->getString('recipient'));
        $subject = trim($data->getString('subject'));
        $body = trim($data->getString('body'));
        if ($recipient === '' || $subject === '' || $body === '') {
            throw new BadRequestHttpException('Empfänger, Betreff und Text sind erforderlich.');
        }
        if (mb_strlen($recipient) > 180 || mb_strlen($subject) > 200 || mb_strlen($body) > 10000) {
            throw new BadRequestHttpException('Die Mail überschreitet die erlaubte Länge.');
        }
        $useCase->execute(new ReplyToMemberMessageRequest($id, $recipient, $subject, $body));

        return new JsonResponse(['status' => 'queued'], JsonResponse::HTTP_ACCEPTED);
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
            'memberEmail' => $message->memberEmail,
        ];
    }
}
