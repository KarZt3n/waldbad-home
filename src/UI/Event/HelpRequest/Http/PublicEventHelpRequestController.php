<?php

namespace App\UI\Event\HelpRequest\Http;

use App\Logic\Event\HelpRequest\UseCase\SubmitEventHelpRequestUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/event-help-requests')]
readonly class PublicEventHelpRequestController
{
    public function __construct(private RateLimiterFactory $eventHelpFormLimiter)
    {
    }

    #[Route('', name: 'api_public_event_help_submit', methods: ['POST'])]
    public function submit(Request $request, SubmitEventHelpRequestUseCase $useCase): JsonResponse
    {
        $data = $request->getPayload();
        $eventIdentifier = trim($data->getString('eventIdentifier'));
        $firstName = trim($data->getString('firstName'));
        $lastName = trim($data->getString('lastName'));
        $message = trim($data->getString('message'));
        $submittedActivityIds = $data->all('activityIds');
        if (!array_is_list($submittedActivityIds) || count($submittedActivityIds) > 20) {
            throw new BadRequestHttpException('Die ausgewählten Aktivitäten sind ungültig.');
        }
        $activityIds = [];
        foreach ($submittedActivityIds as $activityId) {
            if (!is_string($activityId)) {
                throw new BadRequestHttpException('Die ausgewählten Aktivitäten sind ungültig.');
            }
            $activityIds[] = $activityId;
        }
        if ($eventIdentifier === '' || $firstName === '' || $lastName === '' || !$data->getBoolean('privacyAccepted')) {
            throw new BadRequestHttpException('Veranstaltung, Vorname, Nachname und Datenschutzbestätigung sind erforderlich.');
        }
        if (mb_strlen($eventIdentifier) > 80 || mb_strlen($firstName) > 120 || mb_strlen($lastName) > 120 || mb_strlen($message) > 4000) {
            throw new BadRequestHttpException('Die Helferanmeldung überschreitet die erlaubte Länge.');
        }
        $limit = $this->eventHelpFormLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Bitte warten Sie, bevor Sie eine weitere Helferanmeldung senden.');
        }

        $result = $useCase->execute($eventIdentifier, $firstName, $lastName, $message, $activityIds);

        return new JsonResponse([
            'id' => $result->id,
            'message' => 'Vielen Dank. Ihre Helferanmeldung wurde übermittelt.',
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
