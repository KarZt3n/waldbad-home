<?php

namespace App\UI\Event\Activity\Http;

use App\Logic\Event\Activity\Dto\CreateEventActivityRequest;
use App\Logic\Event\Activity\Dto\UpdateEventActivityRequest;
use App\Logic\Event\Activity\Query\ListEventActivitiesQuery;
use App\Logic\Event\Activity\UseCase\CreateEventActivityUseCase;
use App\Logic\Event\Activity\UseCase\UpdateEventActivityUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/event-activities')]
final class AdminEventActivityController extends AbstractController
{
    public function __construct(private readonly EventActivityResponseFactory $responseFactory)
    {
    }

    #[Route('', name: 'api_admin_event_activity_list', methods: ['GET'])]
    public function list(ListEventActivitiesQuery $query): JsonResponse
    {
        if (!$this->isGranted(Permission::ActivitiesView->value) && !$this->isGranted(Permission::PagesView->value)) {
            $this->denyAccessUnlessGranted(Permission::ActivitiesView->value);
        }

        return new JsonResponse($this->responseFactory->collection($query->execute()));
    }

    #[Route('', name: 'api_admin_event_activity_create', methods: ['POST'])]
    public function create(Request $request, CreateEventActivityUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ActivitiesEdit->value);
        $data = $request->getPayload();

        return new JsonResponse($this->responseFactory->activity($useCase->execute(new CreateEventActivityRequest(
            name: $this->name($data->getString('name')),
            description: $this->description($data->getString('description')),
            active: $data->getBoolean('active', true),
        ))), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_event_activity_update', methods: ['PUT'])]
    public function update(string $id, Request $request, UpdateEventActivityUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ActivitiesEdit->value);
        $data = $request->getPayload();

        return new JsonResponse($this->responseFactory->activity($useCase->execute(new UpdateEventActivityRequest(
            id: $id,
            name: $this->name($data->getString('name')),
            description: $this->description($data->getString('description')),
            active: $data->getBoolean('active', true),
        ))));
    }

    private function name(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new BadRequestHttpException('Der Aktivitätsname muss zwischen 1 und 120 Zeichen lang sein.');
        }

        return $name;
    }

    private function description(string $description): string
    {
        $description = trim(strip_tags($description));
        if (mb_strlen($description) > 1000) {
            throw new BadRequestHttpException('Die Aktivitätsbeschreibung darf höchstens 1000 Zeichen lang sein.');
        }

        return $description;
    }
}
