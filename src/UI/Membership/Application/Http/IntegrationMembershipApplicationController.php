<?php

namespace App\UI\Membership\Application\Http;

use App\Logic\Membership\Application\UseCase\ClaimMembershipApplicationsUseCase;
use App\Logic\Membership\Application\UseCase\CompleteMembershipApplicationUseCase;
use App\Logic\Membership\Application\UseCase\FailMembershipApplicationUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/integration/v1/membership-applications')]
readonly class IntegrationMembershipApplicationController
{
    public function __construct(private MembershipApplicationResponseFactory $responseFactory)
    {
    }

    #[Route('/claim', name: 'api_integration_membership_application_claim', methods: ['POST'])]
    public function claim(Request $request, ClaimMembershipApplicationsUseCase $useCase): JsonResponse
    {
        $limit = $request->getPayload()->getInt('limit', 20);

        return new JsonResponse($this->responseFactory->collection($useCase->execute($limit), true));
    }

    #[Route('/{id}/complete', name: 'api_integration_membership_application_complete', methods: ['POST'])]
    public function complete(string $id, Request $request, CompleteMembershipApplicationUseCase $useCase): JsonResponse
    {
        $externalReference = trim($request->getPayload()->getString('externalReference'));
        if ($externalReference === '' || mb_strlen($externalReference) > 180) {
            throw new BadRequestHttpException('Die Fremdsystem-Referenz ist erforderlich oder zu lang.');
        }

        return new JsonResponse($this->responseFactory->application($useCase->execute($id, $externalReference), true));
    }

    #[Route('/{id}/fail', name: 'api_integration_membership_application_fail', methods: ['POST'])]
    public function fail(string $id, Request $request, FailMembershipApplicationUseCase $useCase): JsonResponse
    {
        $reason = trim($request->getPayload()->getString('reason'));
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new BadRequestHttpException('Der Fehlergrund ist erforderlich oder zu lang.');
        }

        return new JsonResponse($this->responseFactory->application($useCase->execute($id, $reason), true));
    }
}
