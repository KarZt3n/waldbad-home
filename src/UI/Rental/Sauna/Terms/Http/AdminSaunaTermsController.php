<?php

namespace App\UI\Rental\Sauna\Terms\Http;

use App\Logic\Rental\Sauna\Terms\Dto\UpdateSaunaTermsRequest;
use App\Logic\Rental\Sauna\Terms\Query\GetSaunaTermsQuery;
use App\Logic\Rental\Sauna\Terms\UseCase\UpdateSaunaTermsUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/sauna-terms')]
final class AdminSaunaTermsController extends AbstractController
{
    public function __construct(private readonly SaunaTermsResponseFactory $responseFactory)
    {
    }

    #[Route('', name: 'api_admin_sauna_terms_show', methods: ['GET'])]
    public function show(GetSaunaTermsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaView->value);

        return new JsonResponse($this->responseFactory->terms($query->execute()));
    }

    #[Route('', name: 'api_admin_sauna_terms_update', methods: ['PUT'])]
    public function update(Request $request, UpdateSaunaTermsUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaEdit->value);
        $data = $request->getPayload();
        $values = [];
        foreach (['priceCents', 'priceUnitMinutes', 'minPersons', 'maxPersons'] as $key) {
            $value = $data->get($key);
            if (!is_int($value)) {
                throw new BadRequestHttpException('Preis, Bezugsdauer sowie Mindest- und Höchstpersonenzahl müssen als ganze Zahlen angegeben werden.');
            }
            $values[$key] = $value;
        }

        return new JsonResponse($this->responseFactory->terms($useCase->execute(new UpdateSaunaTermsRequest(
            priceCents: $values['priceCents'],
            priceUnitMinutes: $values['priceUnitMinutes'],
            minPersons: $values['minPersons'],
            maxPersons: $values['maxPersons'],
        ))));
    }
}
