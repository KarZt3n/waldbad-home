<?php

namespace App\UI\Guestbook\Entry\Http;

use App\Logic\Guestbook\Entry\Query\ListPublishedGuestbookEntriesQuery;
use App\Logic\Guestbook\Entry\UseCase\SubmitGuestbookEntryUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/guestbook-entries')]
readonly class PublicGuestbookController
{
    public function __construct(
        private GuestbookResponseFactory $responseFactory,
        private RateLimiterFactory $guestbookFormLimiter,
    )
    {
    }

    #[Route('', name: 'api_public_guestbook_list', methods: ['GET'])]
    public function list(Request $request, ListPublishedGuestbookEntriesQuery $query): JsonResponse
    {
        $limit = min(50, max(1, $request->query->getInt('limit', 10)));
        $offset = max(0, $request->query->getInt('offset', 0));

        return new JsonResponse($this->responseFactory->publicCollection(
            $query->execute($limit, $offset),
            $query->count(),
        ));
    }

    #[Route('', name: 'api_public_guestbook_submit', methods: ['POST'])]
    public function submit(Request $request, SubmitGuestbookEntryUseCase $useCase): JsonResponse
    {
        $data = $request->getPayload();
        $displayName = trim($data->getString('displayName'));
        $message = trim($data->getString('message'));
        $email = trim($data->getString('email'));

        $limit = $this->guestbookFormLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Bitte warten Sie, bevor Sie einen weiteren Eintrag senden.');
        }

        if ($displayName === '' || $message === '') {
            throw new BadRequestHttpException('Anzeigename und Nachricht sind erforderlich.');
        }
        if (mb_strlen($displayName) > 120 || mb_strlen($message) > 4000) {
            throw new BadRequestHttpException('Der Eintrag überschreitet die erlaubte Länge.');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BadRequestHttpException('Die E-Mail-Adresse ist ungültig.');
        }

        $entry = $useCase->execute($displayName, $email === '' ? null : $email, $message);

        return new JsonResponse([
            'id' => $entry->id,
            'status' => $entry->status->value,
            'message' => 'Der Eintrag wurde zur Moderation eingereicht.',
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
