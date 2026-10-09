<?php

namespace App\UI\Event\HelpRequest\Http;

use App\Logic\Event\HelpRequest\Dto\EventHelpCompanionInput;
use App\Logic\Event\HelpRequest\UseCase\SubmitEventHelpRequestUseCase;
use App\UI\Common\RateLimit\RateLimitMessage;
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
        $email = trim($data->getString('email'));
        $rawBirthDate = trim($data->getString('birthDate'));
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
        if ($eventIdentifier === '' || $firstName === '' || $lastName === '' || $rawBirthDate === '' || !$data->getBoolean('privacyAccepted')) {
            throw new BadRequestHttpException('Veranstaltung, Vorname, Nachname, Geburtsdatum und Datenschutzbestätigung sind erforderlich.');
        }
        $birthDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $rawBirthDate);
        if ($birthDate === false) {
            throw new BadRequestHttpException('Das Geburtsdatum ist ungültig.');
        }
        if (mb_strlen($eventIdentifier) > 80 || mb_strlen($firstName) > 120 || mb_strlen($lastName) > 120
            || mb_strlen($message) > 4000 || mb_strlen($email) > 180) {
            throw new BadRequestHttpException('Die Helferanmeldung überschreitet die erlaubte Länge.');
        }
        $companions = $this->companions($data->all('companions'));
        $limit = $this->eventHelpFormLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            $retryAfterSeconds = $limit->getRetryAfter()->getTimestamp() - time();
            throw new TooManyRequestsHttpException($retryAfterSeconds, RateLimitMessage::exceeded($retryAfterSeconds, formal: true));
        }

        $result = $useCase->execute($eventIdentifier, $firstName, $lastName, $message, $birthDate, $activityIds, $email === '' ? null : $email, $companions);

        return new JsonResponse([
            'id' => $result->id,
            'message' => 'Vielen Dank. Ihre Helferanmeldung wurde übermittelt.',
        ], JsonResponse::HTTP_ACCEPTED);
    }

    /**
     * @param array<mixed> $submitted
     *
     * @return list<EventHelpCompanionInput>
     */
    private function companions(array $submitted): array
    {
        if (!array_is_list($submitted) || count($submitted) > SubmitEventHelpRequestUseCase::MAX_COMPANIONS) {
            throw new BadRequestHttpException('Die mitgebrachten Personen sind ungültig.');
        }
        $companions = [];
        foreach ($submitted as $companion) {
            if (!is_array($companion)) {
                throw new BadRequestHttpException('Die mitgebrachten Personen sind ungültig.');
            }
            $firstName = is_string($companion['firstName'] ?? null) ? trim($companion['firstName']) : '';
            $lastName = is_string($companion['lastName'] ?? null) ? trim($companion['lastName']) : '';
            $rawBirthDate = is_string($companion['birthDate'] ?? null) ? trim($companion['birthDate']) : '';
            if ($firstName === '' || $lastName === '' || $rawBirthDate === '') {
                throw new BadRequestHttpException('Für jede mitgebrachte Person sind Vorname, Nachname und Geburtsdatum erforderlich.');
            }
            if (mb_strlen($firstName) > 120 || mb_strlen($lastName) > 120) {
                throw new BadRequestHttpException('Die Helferanmeldung überschreitet die erlaubte Länge.');
            }
            $birthDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $rawBirthDate);
            if ($birthDate === false) {
                throw new BadRequestHttpException('Das Geburtsdatum einer mitgebrachten Person ist ungültig.');
            }
            $companions[] = new EventHelpCompanionInput($firstName, $lastName, $birthDate);
        }

        return $companions;
    }
}
