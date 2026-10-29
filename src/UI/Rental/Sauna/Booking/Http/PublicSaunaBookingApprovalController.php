<?php

namespace App\UI\Rental\Sauna\Booking\Http;

use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Query\GetSaunaBookingApprovalQuery;
use App\Logic\Rental\Sauna\Booking\UseCase\ApproveSaunaBookingsByTokenUseCase;
use App\Logic\Rental\Sauna\Booking\UseCase\RejectSaunaBookingsByTokenUseCase;
use App\UI\Common\RateLimit\RateLimitMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * „Sauna-Anfrage annehmen/ablehnen“ per Link aus der Benachrichtigungsmail — ohne Login, berechtigt allein
 * durch den Token (siehe `SaunaBookingApprovalToken`). Beide Endpunkte nehmen den Token per POST im
 * Body entgegen, damit er nicht in Server-Logs landet; nur Annehmen und Ablehnen ändern etwas, sodass
 * eine Link-Vorschau des E-Mail-Programms nichts bewirkt.
 */
#[Route('/api/public/v1/sauna-booking-approvals')]
readonly class PublicSaunaBookingApprovalController
{
    public function __construct(private RateLimiterFactory $saunaBookingApprovalLimiter)
    {
    }

    #[Route('/preview', name: 'api_public_sauna_booking_approval_preview', methods: ['POST'])]
    public function preview(Request $request, GetSaunaBookingApprovalQuery $query): JsonResponse
    {
        return new JsonResponse(['items' => array_map(
            $this->summary(...),
            $query->execute($this->token($request)),
        )]);
    }

    /** `{token, dutyRequired}` — `dutyRequired` wie beim Annehmen in der Redaktion (Standard: Dienst nötig). */
    #[Route('', name: 'api_public_sauna_booking_approval_approve', methods: ['POST'])]
    public function approve(Request $request, ApproveSaunaBookingsByTokenUseCase $useCase): JsonResponse
    {
        $accepted = $useCase->execute($this->token($request), $request->getPayload()->getBoolean('dutyRequired', true));

        return new JsonResponse([
            'message' => count($accepted) === 1
                ? 'Die Sauna-Anfrage wurde angenommen.'
                : sprintf('%d Sauna-Termine wurden angenommen.', count($accepted)),
            'items' => array_map($this->summary(...), $accepted),
        ]);
    }

    #[Route('/rejections', name: 'api_public_sauna_booking_approval_reject', methods: ['POST'])]
    public function reject(Request $request, RejectSaunaBookingsByTokenUseCase $useCase): JsonResponse
    {
        $rejected = $useCase->execute($this->token($request));

        return new JsonResponse([
            'message' => count($rejected) === 1
                ? 'Die Sauna-Anfrage wurde abgelehnt.'
                : sprintf('%d Sauna-Termine wurden abgelehnt.', count($rejected)),
            'items' => array_map($this->summary(...), $rejected),
        ]);
    }

    private function token(Request $request): string
    {
        $token = trim($request->getPayload()->getString('token'));
        if ($token === '') {
            throw new BadRequestHttpException('Der Link ist unvollständig.');
        }
        $limit = $this->saunaBookingApprovalLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            $retryAfterSeconds = $limit->getRetryAfter()->getTimestamp() - time();
            throw new TooManyRequestsHttpException($retryAfterSeconds, RateLimitMessage::exceeded($retryAfterSeconds));
        }

        return $token;
    }

    /**
     * Nur was zum Entscheiden nötig ist — ohne E-Mail-Adresse, Geburtsdatum und Mitgliedsdaten.
     *
     * @return array{date: string, startTime: string, endTime: string, personCount: int, priceCents: int, firstName: string, lastName: string, message: string, status: string}
     */
    private function summary(SaunaBookingResponse $booking): array
    {
        return [
            'date' => $booking->date->format('Y-m-d'),
            'startTime' => $booking->startTime,
            'endTime' => $booking->endTime,
            'personCount' => $booking->personCount,
            'priceCents' => $booking->priceCents,
            'firstName' => $booking->firstName,
            'lastName' => $booking->lastName,
            'message' => $booking->message,
            'status' => $booking->status->value,
        ];
    }
}
