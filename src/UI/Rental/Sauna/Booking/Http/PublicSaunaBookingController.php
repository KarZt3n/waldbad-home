<?php

namespace App\UI\Rental\Sauna\Booking\Http;

use App\Logic\Rental\Sauna\Booking\Dto\GetSaunaCalendarRequest;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaParticipantInput;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaRequestDayInput;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitIndividualSaunaRequest;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitSaunaBookingRequest;
use App\Logic\Rental\Sauna\Booking\Query\GetSaunaCalendarQuery;
use App\Logic\Rental\Sauna\Booking\UseCase\SubmitIndividualSaunaRequestUseCase;
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

    /**
     * Zwei Anfragearten: aus dem Kalender genau ein Termin (`date`/`startTime`/`endTime`,
     * `personCount`), individuell (`individual: true`) ein oder mehrere Wunschtage (`days`), je Tag
     * mit Personenzahl und den Namen aller Personen.
     */
    #[Route('/bookings', name: 'api_public_sauna_booking_submit', methods: ['POST'])]
    public function submit(Request $request, SubmitSaunaBookingUseCase $useCase, SubmitIndividualSaunaRequestUseCase $individualUseCase): JsonResponse
    {
        $data = $request->getPayload();
        $firstName = trim($data->getString('firstName'));
        $lastName = trim($data->getString('lastName'));
        $rawBirthDate = trim($data->getString('birthDate'));
        $email = trim($data->getString('email'));
        $message = trim($data->getString('message'));
        if ($firstName === '' || $lastName === '' || $rawBirthDate === '' || !$data->getBoolean('privacyAccepted')) {
            throw new BadRequestHttpException('Termin, Vorname, Nachname, Geburtsdatum und Datenschutzbestätigung sind erforderlich.');
        }
        if (mb_strlen($firstName) > 120 || mb_strlen($lastName) > 120 || mb_strlen($email) > 180 || mb_strlen($message) > 2000) {
            throw new BadRequestHttpException('Die Sauna-Anmeldung überschreitet die erlaubte Länge.');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BadRequestHttpException('Die E-Mail-Adresse ist ungültig.');
        }
        $birthDate = $this->date($rawBirthDate, 'Das Geburtsdatum');
        $individual = $data->getBoolean('individual');
        $days = $individual ? $this->days($data->all('days')) : [];
        if (!$individual) {
            $personCount = $data->get('personCount');
            if (!is_int($personCount)) {
                throw new BadRequestHttpException('Die Personenzahl muss als ganze Zahl angegeben werden.');
            }
            [$date, $startTime, $endTime] = $this->schedule($data->getString('date'), $data->getString('startTime'), $data->getString('endTime'));
        }

        $limit = $this->saunaBookingLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            $retryAfterSeconds = $limit->getRetryAfter()->getTimestamp() - time();
            throw new TooManyRequestsHttpException($retryAfterSeconds, RateLimitMessage::exceeded($retryAfterSeconds, formal: true));
        }

        if ($days !== []) {
            $result = $individualUseCase->execute(new SubmitIndividualSaunaRequest(
                firstName: $firstName,
                lastName: $lastName,
                birthDate: $birthDate,
                email: $email === '' ? null : $email,
                message: $message,
                days: $days,
            ));

            return new JsonResponse([
                'id' => $result->requestId,
                'message' => 'Vielen Dank. Ihre Sauna-Anfrage wurde übermittelt und wird vom Verein geprüft.',
            ], JsonResponse::HTTP_ACCEPTED);
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
        ));

        return new JsonResponse([
            'id' => $result->id,
            'message' => 'Vielen Dank. Ihre Sauna-Anfrage wurde übermittelt und wird vom Verein geprüft.',
        ], JsonResponse::HTTP_ACCEPTED);
    }

    /**
     * @param array<mixed> $rawDays
     *
     * @return non-empty-list<SaunaRequestDayInput>
     */
    private function days(array $rawDays): array
    {
        $days = [];
        foreach ($rawDays as $rawDay) {
            if (!is_array($rawDay)) {
                throw new BadRequestHttpException('Die Wunschtage sind ungültig.');
            }
            $personCount = $rawDay['personCount'] ?? null;
            $rawParticipants = $rawDay['participants'] ?? null;
            if (!is_int($personCount) || !is_array($rawParticipants)) {
                throw new BadRequestHttpException('Für jeden Wunschtag sind die Personenzahl und die Namen der Personen erforderlich.');
            }
            [$date, $startTime, $endTime] = $this->schedule($this->text($rawDay, 'date'), $this->text($rawDay, 'startTime'), $this->text($rawDay, 'endTime'));
            $participants = [];
            foreach ($rawParticipants as $rawParticipant) {
                if (!is_array($rawParticipant)) {
                    throw new BadRequestHttpException('Die Namen der Personen sind ungültig.');
                }
                $participants[] = new SaunaParticipantInput($this->text($rawParticipant, 'firstName'), $this->text($rawParticipant, 'lastName'));
            }
            $days[] = new SaunaRequestDayInput($date, $startTime, $endTime, $personCount, $participants);
        }
        if ($days === []) {
            throw new BadRequestHttpException('Bitte mindestens einen Wunschtag angeben.');
        }

        return $days;
    }

    /**
     * @param array<mixed> $data
     */
    private function text(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return array{\DateTimeImmutable, string, string}
     */
    private function schedule(string $rawDate, string $rawStartTime, string $rawEndTime): array
    {
        $startTime = trim($rawStartTime);
        $endTime = trim($rawEndTime);
        if (trim($rawDate) === '' || $startTime === '' || $endTime === '') {
            throw new BadRequestHttpException('Termin, Vorname, Nachname, Geburtsdatum und Datenschutzbestätigung sind erforderlich.');
        }
        $timePattern = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';
        if (preg_match($timePattern, $startTime) !== 1 || preg_match($timePattern, $endTime) !== 1) {
            throw new BadRequestHttpException('Die Uhrzeiten müssen im Format HH:MM angegeben werden.');
        }

        return [$this->date(trim($rawDate), 'Das Datum'), $startTime, $endTime];
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
