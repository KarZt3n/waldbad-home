<?php

namespace App\UI\Event\Template\Http;

use App\Logic\Event\Template\Query\ListEventTemplatesQuery;
use App\Logic\Event\Template\UseCase\CreateEventTemplateUseCase;
use App\Logic\Event\Template\UseCase\DeleteEventTemplateUseCase;
use App\Logic\Event\Template\UseCase\UpdateEventTemplateUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Wiederverwendbare Vorlagen für Veranstaltungen/Arbeitseinsätze (siehe `EventTemplate`) — bewusst
 * unter derselben Berechtigung wie `AdminEventScheduleController`: wer Veranstaltungen anlegen darf,
 * darf auch die Vorlagen dafür pflegen.
 */
#[Route('/api/admin/v1/event-templates')]
final class AdminEventTemplateController extends AbstractController
{
    public function __construct(
        private readonly EventTemplateResponseFactory $responseFactory,
        private readonly EventTemplateRequestMapper $requestMapper,
    ) {
    }

    #[Route('', name: 'api_admin_event_template_list', methods: ['GET'])]
    public function list(ListEventTemplatesQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventsView->value);

        return new JsonResponse($this->responseFactory->collection($query->execute()));
    }

    #[Route('', name: 'api_admin_event_template_create', methods: ['POST'])]
    public function create(Request $request, CreateEventTemplateUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventsEdit->value);

        return new JsonResponse(
            $this->responseFactory->template($useCase->execute($this->requestMapper->create($request))),
            JsonResponse::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_admin_event_template_update', methods: ['PUT'])]
    public function update(string $id, Request $request, UpdateEventTemplateUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventsEdit->value);

        return new JsonResponse(
            $this->responseFactory->template($useCase->execute($this->requestMapper->update($id, $request))),
        );
    }

    #[Route('/{id}', name: 'api_admin_event_template_delete', methods: ['DELETE'])]
    public function delete(string $id, DeleteEventTemplateUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventsEdit->value);
        $useCase->execute($id);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
