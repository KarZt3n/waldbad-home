<?php

namespace App\UI\Rental\Sauna\Booking\Http;

use App\Logic\Rental\Sauna\Booking\Dto\GetSaunaCalendarRequest;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitSaunaBookingRequest;
use App\Logic\Rental\Sauna\Booking\Query\GetSaunaCalendarQuery;
use App\Logic\Rental\Sauna\Booking\UseCase\SubmitSaunaBookingUseCase;
use App\UI\Common\RateLimit\RateLimitMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/sauna')]
readonly class PublicSaunaBookingController
{
    private const int DEFAULT_CALENDAR_DAYS = 7;

    public function __construct(
        private RateLimiterFactory $saunaBookingLimiter,
        private SaunaBookingResponseFactory $responseFactory,
    ) {
    }

    #[Route('/calendar', name: 'api_public_sauna_calendar', methods: ['GET'])]
    public function calendar(Request $request, GetSaunaCalendarQuery $query): JsonResponse
    {
        $rawFrom = trim($request->query->getString('from'));
        $from = $rawFrom === '' ? new \DateTimeImmutable('today') : $this->date($rawFrom, 'Das Startdatum');
        $days = $request->query->getInt('days', self::DEFAULT_CALENDAR_DAYS);

        return new JsonResponse($this->responseFactory->calendar($query->execute(new GetSaunaCalendarRequest($from, $days))));
    }

    #[Route('/bookings', name: 'api_public_sauna_booking_submit', methods: ['POST'])]
    public function submit(Request $request, SubmitSaunaBookingUseCase $useCase): JsonResponse
    {
        $data = $request->getPayload();
        $rawDate = trim($data->getString('date'));
        $startTime = trim($data->getString('startTime'));
        $endTime = trim($data->getString('endTime'));
        $personCount = $data->get('personCount');
        $firstName = trim($data->getString('firstName'));
        $lastName = trim($data->getString('lastName'));
        $rawBirthDate = trim($data->getString('birthDate'));
        $email = trim($data->getString('email'));
        $message = trim($data->getString('message'));
        if ($rawDate === '' || $startTime === '' || $endTime === '' || $firstName === '' || $lastName === ''
            || $rawBirthDate === '' || !$data->getBoolean('privacyAccepted')) {
            throw new BadRequestHttpException('Termin, Vorname, Nachname, Geburtsdatum und Datenschutzbestätigung sind erforderlich.');
        }
        if (!is_int($personCount)) {
            throw new BadRequestHttpException('Die Personenzahl muss als ganze Zahl angegeben werden.');
        }
        $timePattern = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';
        if (preg_match($timePattern, $startTime) !== 1 || preg_match($timePattern, $endTime) !== 1) {
            throw new BadRequestHttpException('Die Uhrzeiten müssen im Format HH:MM angegeben werden.');
        }
        if (mb_strlen($firstName) > 120 || mb_strlen($lastName) > 120 || mb_strlen($email) > 180 || mb_strlen($message) > 2000) {
            throw new BadRequestHttpException('Die Sauna-Anmeldung überschreitet die erlaubte Länge.');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BadRequestHttpException('Die E-Mail-Adresse ist ungültig.');
        }
        $date = $this->date($rawDate, 'Das Datum');
        $birthDate = $this->date($rawBirthDate, 'Das Geburtsdatum');

        $limit = $this->saunaBookingLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            $retryAfterSeconds = $limit->getRetryAfter()->getTimestamp() - time();
            throw new TooManyRequestsHttpException($retryAfterSeconds, RateLimitMessage::exceeded($retryAfterSeconds, formal: true));
        }

        $result = $useCase->execute(new SubmitSaunaBookingRequest(
            date: $date,
            startTime: $startTime,
            endTime: $endTime,
            personCount: $personCount,
            firstName: $firstName,
            lastName: $lastName,
            birthDate: $birthDate,
            email: $email === '' ? null : $email,
            message: $message,
            individual: $data->getBoolean('individual'),
        ));

        return new JsonResponse([
            'id' => $result->id,
            'message' => 'Vielen Dank. Ihre Sauna-Anfrage wurde übermittelt und wird vom Verein geprüft.',
        ], JsonResponse::HTTP_ACCEPTED);
    }

    private function date(string $raw, string $label): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($date === false || $date->format('Y-m-d') !== $raw) {
            throw new BadRequestHttpException(sprintf('%s muss ein gültiges Datum im Format JJJJ-MM-TT sein.', $label));
        }

        return $date;
    }
}
