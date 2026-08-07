<?php

namespace App\UI\Membership\Application\Http;

use App\Logic\Membership\Application\UseCase\SubmitMembershipApplicationUseCase;
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
            throw new TooManyRequestsHttpException(
                $limit->getRetryAfter()->getTimestamp() - time(),
                'Bitte warten Sie, bevor Sie einen weiteren Mitgliedsantrag senden.',
            );
        }
        $result = $useCase->execute($this->requestMapper->submit($request));

        return new JsonResponse([
            'id' => $result->id,
            'status' => $result->status->value,
            'message' => 'Vielen Dank. Der Mitgliedsantrag wurde sicher übermittelt.',
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
