<?php

namespace App\UI\Event\Schedule\Http;

use App\Logic\Event\Schedule\Query\ListEventSchedulesQuery;
use App\Logic\Event\Schedule\UseCase\CreateEventScheduleUseCase;
use App\Logic\Event\Schedule\UseCase\DeleteEventScheduleUseCase;
use App\Logic\Event\Schedule\UseCase\UpdateEventScheduleUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/events')]
final class AdminEventScheduleController extends AbstractController
{
    public function __construct(
        private readonly EventScheduleResponseFactory $responseFactory,
        private readonly EventScheduleRequestMapper $requestMapper,
    ) {
    }

    #[Route('', name: 'api_admin_event_schedule_list', methods: ['GET'])]
    public function list(ListEventSchedulesQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventsView->value);

        return new JsonResponse($this->responseFactory->collection($query->execute()));
    }

    #[Route('', name: 'api_admin_event_schedule_create', methods: ['POST'])]
    public function create(Request $request, CreateEventScheduleUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventsEdit->value);

        return new JsonResponse(
            $this->responseFactory->schedule($useCase->execute($this->requestMapper->create($request))),
            JsonResponse::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_admin_event_schedule_update', methods: ['PUT'])]
    public function update(string $id, Request $request, UpdateEventScheduleUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventsEdit->value);

        return new JsonResponse(
            $this->responseFactory->schedule($useCase->execute($this->requestMapper->update($id, $request))),
        );
    }

    #[Route('/{id}', name: 'api_admin_event_schedule_delete', methods: ['DELETE'])]
    public function delete(string $id, DeleteEventScheduleUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventsEdit->value);
        $useCase->execute($id);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
