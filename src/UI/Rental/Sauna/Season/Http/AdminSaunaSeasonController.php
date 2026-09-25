<?php

namespace App\UI\Rental\Sauna\Season\Http;

use App\Logic\Rental\Sauna\Season\Query\ListSaunaSeasonsQuery;
use App\Logic\Rental\Sauna\Season\UseCase\CloseSaunaSeasonUseCase;
use App\Logic\Rental\Sauna\Season\UseCase\CreateSaunaSeasonUseCase;
use App\Logic\Rental\Sauna\Season\UseCase\DeleteSaunaSeasonUseCase;
use App\Logic\Rental\Sauna\Season\UseCase\UpdateSaunaSeasonUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/sauna-seasons')]
final class AdminSaunaSeasonController extends AbstractController
{
    public function __construct(
        private readonly SaunaSeasonResponseFactory $responseFactory,
        private readonly SaunaSeasonRequestMapper $requestMapper,
    ) {
    }

    #[Route('', name: 'api_admin_sauna_season_list', methods: ['GET'])]
    public function list(ListSaunaSeasonsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaView->value);

        return new JsonResponse($this->responseFactory->collection($query->execute()));
    }

    #[Route('', name: 'api_admin_sauna_season_create', methods: ['POST'])]
    public function create(Request $request, CreateSaunaSeasonUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaEdit->value);

        return new JsonResponse(
            $this->responseFactory->season($useCase->execute($this->requestMapper->create($request))),
            JsonResponse::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_admin_sauna_season_update', methods: ['PUT'])]
    public function update(string $id, Request $request, UpdateSaunaSeasonUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaEdit->value);

        return new JsonResponse($this->responseFactory->season($useCase->execute($this->requestMapper->update($id, $request))));
    }

    #[Route('/{id}/close', name: 'api_admin_sauna_season_close', methods: ['POST'])]
    public function close(string $id, CloseSaunaSeasonUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaEdit->value);

        return new JsonResponse($this->responseFactory->season($useCase->execute($id)));
    }

    #[Route('/{id}', name: 'api_admin_sauna_season_delete', methods: ['DELETE'])]
    public function delete(string $id, DeleteSaunaSeasonUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaEdit->value);
        $useCase->execute($id);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
