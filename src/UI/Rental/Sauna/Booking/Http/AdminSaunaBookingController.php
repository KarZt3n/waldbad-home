<?php

namespace App\UI\Rental\Sauna\Booking\Http;

use App\Logic\Rental\Sauna\Booking\Query\ListSaunaBookingsQuery;
use App\Logic\Rental\Sauna\Booking\UseCase\AcceptSaunaBookingUseCase;
use App\Logic\Rental\Sauna\Booking\UseCase\AcceptSaunaRequesterBookingsUseCase;
use App\Logic\Rental\Sauna\Booking\UseCase\CancelSaunaBookingUseCase;
use App\Logic\Rental\Sauna\Booking\UseCase\RejectSaunaBookingUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

    /** „Alle annehmen“: alle offenen Anmeldungen einer anfragenden Person (`requesterKey` aus der Liste). */
    #[Route('/requesters/{requesterKey}/accept', name: 'api_admin_sauna_requester_accept', methods: ['POST'])]
    public function acceptRequester(string $requesterKey, AcceptSaunaRequesterBookingsUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaEdit->value);

        return new JsonResponse($this->responseFactory->collection($useCase->execute($requesterKey)));
    }

    /** Optional `{notify: false}`, um keine Stornobestätigung an die anfragende Person zu senden (Standard: senden). */
    #[Route('/{id}/cancel', name: 'api_admin_sauna_booking_cancel', methods: ['POST'])]
    public function cancel(string $id, Request $request, CancelSaunaBookingUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaEdit->value);
        $notify = $request->getContent() === '' ? true : $request->getPayload()->getBoolean('notify', true);

        return new JsonResponse($this->responseFactory->booking($useCase->execute($id, $notify)));
    }

    #[Route('/{id}/reject', name: 'api_admin_sauna_booking_reject', methods: ['POST'])]
    public function reject(string $id, RejectSaunaBookingUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaEdit->value);

        return new JsonResponse($this->responseFactory->booking($useCase->execute($id)));
    }
}
