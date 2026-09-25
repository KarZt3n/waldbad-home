<?php

namespace App\UI\Rental\Sauna\Booking\Http;

use App\Logic\Rental\Sauna\Booking\Query\ListSaunaBookingsQuery;
use App\Logic\Rental\Sauna\Booking\UseCase\AcceptSaunaBookingUseCase;
use App\Logic\Rental\Sauna\Booking\UseCase\RejectSaunaBookingUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/sauna-bookings')]
final class AdminSaunaBookingController extends AbstractController
{
    public function __construct(private readonly SaunaBookingResponseFactory $responseFactory)
    {
    }

    #[Route('', name: 'api_admin_sauna_booking_list', methods: ['GET'])]
    public function list(ListSaunaBookingsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaView->value);

        return new JsonResponse($this->responseFactory->collection($query->execute()));
    }

    #[Route('/{id}/accept', name: 'api_admin_sauna_booking_accept', methods: ['POST'])]
    public function accept(string $id, AcceptSaunaBookingUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaEdit->value);

        return new JsonResponse($this->responseFactory->booking($useCase->execute($id)));
    }

    #[Route('/{id}/reject', name: 'api_admin_sauna_booking_reject', methods: ['POST'])]
    public function reject(string $id, RejectSaunaBookingUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaEdit->value);

        return new JsonResponse($this->responseFactory->booking($useCase->execute($id)));
    }
}
