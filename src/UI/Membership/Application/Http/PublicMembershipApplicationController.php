<?php

namespace App\UI\Membership\Application\Http;

use App\Logic\Membership\Application\UseCase\SubmitMembershipApplicationUseCase;
use App\UI\Common\RateLimit\RateLimitMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/membership-applications')]
readonly class PublicMembershipApplicationController
{
    public function __construct(
        private RateLimiterFactory $membershipApplicationLimiter,
        private MembershipApplicationRequestMapper $requestMapper,
    ) {
    }

    #[Route('', name: 'api_public_membership_application_submit', methods: ['POST'])]
    public function submit(Request $request, SubmitMembershipApplicationUseCase $useCase): JsonResponse
    {
        $limit = $this->membershipApplicationLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            $retryAfterSeconds = $limit->getRetryAfter()->getTimestamp() - time();
            throw new TooManyRequestsHttpException(
                $retryAfterSeconds,
                RateLimitMessage::exceeded($retryAfterSeconds, formal: true),
            );
        }
        $result = $useCase->execute($this->requestMapper->submit($request));

        return new JsonResponse([
            'id' => $result->id,
            'message' => 'Vielen Dank. Der Mitgliedsantrag wurde sicher übermittelt.',
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
