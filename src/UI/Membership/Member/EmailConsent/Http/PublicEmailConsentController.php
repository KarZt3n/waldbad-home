<?php

namespace App\UI\Membership\Member\EmailConsent\Http;

use App\Logic\Membership\Member\EmailConsent\UseCase\ConfirmMemberEmailConsentUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Löst den per Mail verschickten Doppel-Opt-in-Bestätigungslink für die E-Mail-Einwilligung ein
 * (siehe `MemberEmailConsentToken`, `ConfirmMemberEmailConsentUseCase`) — komplett ohne
 * Login/Session, wie `PublicMemberAccessController`.
 */
#[Route('/api/public/v1/email-consent')]
readonly class PublicEmailConsentController
{
    public function __construct(private RateLimiterFactory $emailConsentConfirmLimiter)
    {
    }

    #[Route('/confirmations', name: 'api_public_email_consent_confirm', methods: ['POST'])]
    public function confirm(Request $request, ConfirmMemberEmailConsentUseCase $useCase): JsonResponse
    {
        $token = trim($request->getPayload()->getString('token'));
        if ($token === '') {
            throw new BadRequestHttpException('Der Bestätigungslink ist unvollständig.');
        }

        $limit = $this->emailConsentConfirmLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Bitte warte einen Moment.');
        }

        $useCase->execute($token);

        return new JsonResponse([
            'message' => 'Danke! Die E-Mail-Einwilligung wurde bestätigt.',
        ]);
    }
}
