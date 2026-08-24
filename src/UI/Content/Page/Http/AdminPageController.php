<?php

namespace App\UI\Content\Page\Http;

use App\Logic\Content\Page\Query\ListPagesQuery;
use App\Logic\Content\Page\UseCase\ChangePageStatusUseCase;
use App\Logic\Content\Page\UseCase\CreatePageUseCase;
use App\Logic\Content\Page\UseCase\DeletePageUseCase;
use App\Logic\Content\Page\UseCase\DuplicatePageUseCase;
use App\Logic\Content\Page\UseCase\MovePageUseCase;
use App\Logic\Content\Page\UseCase\PreviewPageUseCase;
use App\Logic\Content\Page\UseCase\ReorderPageUseCase;
use App\Logic\Content\Page\UseCase\UpdatePageUseCase;
use App\UI\Common\Http\ApiResponseFactory;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/pages')]
class AdminPageController extends AbstractController
{
    public function __construct(
        private readonly ApiResponseFactory $responseFactory,
        private readonly PageRequestMapper $requestMapper,
        private readonly PageStructureRequestMapper $structureRequestMapper,
        private readonly DuplicatePageUseCase $duplicatePage,
        private readonly MovePageUseCase $movePage,
        private readonly DeletePageUseCase $deletePage,
    ) {
    }

    #[Route('', name: 'api_admin_pages_list', methods: ['GET'])]
    public function list(ListPagesQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CmsRead->value);

        return $this->responseFactory->pages($query->execute());
    }

    #[Route('', name: 'api_admin_pages_create', methods: ['POST'])]
    public function create(Request $request, CreatePageUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContentEdit->value);

        return $this->responseFactory->page(
            $useCase->execute($this->requestMapper->create($request)),
            JsonResponse::HTTP_CREATED,
        );
    }

    #[Route('/preview', name: 'api_admin_pages_preview', methods: ['POST'])]
    public function preview(Request $request, PreviewPageUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContentEdit->value);

        return $this->responseFactory->page($useCase->execute($this->requestMapper->create($request)));
    }

    #[Route('/{id}', name: 'api_admin_pages_update', methods: ['PUT'])]
    public function update(string $id, Request $request, UpdatePageUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContentEdit->value);

        return $this->responseFactory->page($useCase->execute($this->requestMapper->update($id, $request)));
    }

    #[Route('/{id}/duplicate', name: 'api_admin_pages_duplicate', methods: ['POST'])]
    public function duplicate(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContentEdit->value);

        return $this->responseFactory->page($this->duplicatePage->execute($id), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}/move/{direction}', name: 'api_admin_pages_move', requirements: ['direction' => 'up|down'], methods: ['POST'])]
    public function move(string $id, string $direction): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContentEdit->value);

        return $this->responseFactory->page($this->movePage->execute($id, $direction));
    }

    #[Route('/{id}/position', name: 'api_admin_pages_position', methods: ['PUT'])]
    public function position(string $id, Request $request, ReorderPageUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContentEdit->value);

        return $this->responseFactory->page($useCase->execute($this->structureRequestMapper->reorder($id, $request)));
    }

    #[Route('/{id}', name: 'api_admin_pages_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContentEdit->value);
        $this->deletePage->execute($id);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/{action}', name: 'api_admin_pages_status', requirements: ['action' => 'request-review|publish|unpublish|archive'], methods: ['POST'])]
    public function status(string $id, string $action, ChangePageStatusUseCase $useCase): JsonResponse
    {
        $permission = $action === 'request-review' ? Permission::ContentEdit : Permission::ContentPublish;
        $this->denyAccessUnlessGranted($permission->value);

        return $this->responseFactory->page($useCase->execute($id, $action));
    }
}
