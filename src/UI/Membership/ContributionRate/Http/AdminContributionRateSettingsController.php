<?php

namespace App\UI\Membership\ContributionRate\Http;

use App\Logic\Membership\ContributionRate\Dto\ContributionRateSettingsResponse;
use App\Logic\Membership\ContributionRate\Query\GetContributionRateSettingsQuery;
use App\Logic\Membership\ContributionRate\UseCase\UpdateContributionRateSettingsUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Das gemeinsame „gültig ab" für alle Beitragssätze (siehe `ContributionRateSettings`) — bewusst
 * getrennt von den einzelnen Beitragssätzen selbst (`AdminContributionRateController`), da es für
 * alle gemeinsam gilt statt je Satz.
 */
#[Route('/api/admin/v1/contribution-rate-settings')]
class AdminContributionRateSettingsController extends AbstractController
{
    #[Route('', name: 'api_admin_contribution_rate_settings_get', methods: ['GET'])]
    public function get(GetContributionRateSettingsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContributionRatesView->value);

        return new JsonResponse($this->toArray($query->execute()));
    }

    #[Route('', name: 'api_admin_contribution_rate_settings_update', methods: ['PUT'])]
    public function update(Request $request, UpdateContributionRateSettingsUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContributionRatesEdit->value);

        return new JsonResponse($this->toArray($useCase->execute($this->optionalDate($request, 'validFrom'))));
    }

    private function optionalDate(Request $request, string $key): ?\DateTimeImmutable
    {
        $value = trim($request->getPayload()->getString($key));
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false) {
            throw new BadRequestHttpException(sprintf('Das Datum im Feld "%s" ist ungültig.', $key));
        }

        return $date;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(ContributionRateSettingsResponse $response): array
    {
        return ['validFrom' => $response->validFrom];
    }
}
