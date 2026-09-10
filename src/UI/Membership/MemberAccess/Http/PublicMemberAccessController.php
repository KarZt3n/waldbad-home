<?php

namespace App\UI\Membership\MemberAccess\Http;

use App\Logic\Membership\MemberAccess\Dto\MemberAccessSessionResponse;
use App\Logic\Membership\MemberAccess\Dto\MemberSelfServiceResponse;
use App\Logic\Membership\MemberAccess\UseCase\RequestMemberAccessUseCase;
use App\Logic\Membership\MemberAccess\UseCase\ResolveMemberAccessSessionUseCase;
use App\Logic\Membership\MemberMessage\UseCase\SendMemberMessageUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * „Meine Mitgliedschaft": ein per E-Mail-Adresse angeforderter, 30 Minuten gültiger Zugangslink
 * (siehe `RequestMemberAccessUseCase`), über den die eigenen — nur lesbaren — Mitgliedsdaten
 * eingesehen (`ResolveMemberAccessSessionUseCase`) und eine Nachricht an den Verein gesendet werden
 * kann (`SendMemberMessageUseCase`). Komplett ohne Login/Session: jeder Aufruf prüft den Token
 * erneut.
 */
#[Route('/api/public/v1/member-access')]
readonly class PublicMemberAccessController
{
    public function __construct(
        private RateLimiterFactory $memberAccessRequestLimiter,
        private RateLimiterFactory $memberAccessSessionLimiter,
        private RateLimiterFactory $memberMessageLimiter,
    ) {
    }

    /**
     * Fordert einen Zugangslink an. Antwortet absichtlich immer gleich (auch wenn die Kombination
     * aus E-Mail-Adresse und Geburtsdatum zu keinem Mitglied gehört) — siehe
     * `RequestMemberAccessUseCase`.
     */
    #[Route('/tokens', name: 'api_public_member_access_request', methods: ['POST'])]
    public function requestAccess(Request $request, RequestMemberAccessUseCase $useCase): JsonResponse
    {
        $email = trim($request->getPayload()->getString('email'));
        $birthDate = trim($request->getPayload()->getString('birthDate'));
        if ($email === '' || $birthDate === '') {
            throw new BadRequestHttpException('E-Mail-Adresse und Geburtsdatum sind erforderlich.');
        }

        $limit = $this->memberAccessRequestLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Bitte warte, bevor du einen weiteren Zugang anforderst.');
        }

        $useCase->execute($email, $birthDate);

        return new JsonResponse([
            'message' => 'Falls diese E-Mail-Adresse als Mitglied hinterlegt ist, wurde soeben ein Zugangslink verschickt.',
        ], JsonResponse::HTTP_ACCEPTED);
    }

    /**
     * Löst einen Token in die zugehörigen Mitgliedsdaten auf. Verlangt zusätzlich zum Token das per
     * Mail mitgeschickte Passwort (siehe `MemberAccessToken`) — ohne Unterschied in der
     * Fehlermeldung, welcher der beiden Faktoren nicht passt.
     */
    #[Route('/sessions', name: 'api_public_member_access_session', methods: ['POST'])]
    public function resolveSession(Request $request, ResolveMemberAccessSessionUseCase $useCase): JsonResponse
    {
        $token = trim($request->getPayload()->getString('token'));
        $password = trim($request->getPayload()->getString('password'));
        if ($token === '' || $password === '') {
            throw new BadRequestHttpException('Token und Passwort sind erforderlich.');
        }

        $limit = $this->memberAccessSessionLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Bitte warte einen Moment.');
        }

        return new JsonResponse($this->sessionToArray($useCase->execute($token, $password)));
    }

    /**
     * Sendet eine Nachricht an den Verein, gebunden an ein über den Token erreichbares Mitglied.
     */
    #[Route('/messages', name: 'api_public_member_access_message', methods: ['POST'])]
    public function sendMessage(Request $request, SendMemberMessageUseCase $useCase): JsonResponse
    {
        $data = $request->getPayload();
        $token = trim($data->getString('token'));
        $password = trim($data->getString('password'));
        $memberId = trim($data->getString('memberId'));
        $message = trim($data->getString('message'));
        if ($token === '' || $password === '' || $memberId === '' || $message === '') {
            throw new BadRequestHttpException('Token, Passwort, Mitglied und Nachricht sind erforderlich.');
        }
        if (mb_strlen($message) > 4000) {
            throw new BadRequestHttpException('Die Nachricht überschreitet die erlaubte Länge.');
        }

        $limit = $this->memberMessageLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Bitte warte, bevor du eine weitere Nachricht sendest.');
        }

        $useCase->execute($token, $password, $memberId, $message);

        return new JsonResponse([
            'message' => 'Vielen Dank. Deine Nachricht wurde übermittelt.',
        ], JsonResponse::HTTP_ACCEPTED);
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionToArray(MemberAccessSessionResponse $session): array
    {
        return [
            'email' => $session->email,
            'members' => array_map($this->memberToArray(...), $session->members),
            'contributionRatesValidFrom' => $session->contributionRatesValidFrom,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memberToArray(MemberSelfServiceResponse $member): array
    {
        return [
            'id' => $member->id,
            'memberNumber' => $member->memberNumber,
            'primaryMemberNumber' => $member->primaryMemberNumber,
            'salutation' => $member->salutation,
            'firstName' => $member->firstName,
            'lastName' => $member->lastName,
            'birthDate' => $member->birthDate,
            'familyRole' => $member->familyRole,
            'street' => $member->street,
            'postalCode' => $member->postalCode,
            'city' => $member->city,
            'email' => $member->email,
            'phone' => $member->phone,
            'function' => $member->function,
            'active' => $member->active,
            'joinedAt' => $member->joinedAt,
            'leftAt' => $member->leftAt,
            'contributionLiable' => $member->contributionLiable,
            'contributionCategoryLabel' => $member->contributionCategoryLabel,
            'contributionAmountCents' => $member->contributionAmountCents,
            'workAssignmentSurchargeCents' => $member->workAssignmentSurchargeCents,
            'paymentMethod' => $member->paymentMethod,
            'paymentInterval' => $member->paymentInterval,
            'paymentDay' => $member->paymentDay,
        ];
    }
}
