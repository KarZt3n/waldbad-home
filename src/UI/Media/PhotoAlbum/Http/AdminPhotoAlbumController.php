<?php

namespace App\UI\Media\PhotoAlbum\Http;

use App\Logic\Media\PhotoAlbum\Query\ListPhotoAlbumsQuery;
use App\Logic\Media\PhotoAlbum\UseCase\DeletePhotoAlbumUseCase;
use App\Logic\Media\PhotoAlbum\UseCase\SavePhotoAlbumUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/photo-albums')]
final class AdminPhotoAlbumController extends AbstractController
{
    public function __construct(
        private readonly PhotoAlbumResponseFactory $responseFactory,
        private readonly PhotoAlbumRequestMapper $requestMapper,
    ) {
    }

    #[Route('', name: 'api_admin_photo_album_list', methods: ['GET'])]
    public function list(ListPhotoAlbumsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PhotosView->value);

        return new JsonResponse($this->responseFactory->collection($query->execute()));
    }

    #[Route('', name: 'api_admin_photo_album_create', methods: ['POST'])]
    public function create(Request $request, SavePhotoAlbumUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PhotosEdit->value);

        return new JsonResponse(
            $this->responseFactory->album($useCase->execute($this->requestMapper->fromArray(null, $request->getPayload()->all()))),
            JsonResponse::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_admin_photo_album_update', methods: ['PUT'])]
    public function update(string $id, Request $request, SavePhotoAlbumUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PhotosEdit->value);

        return new JsonResponse($this->responseFactory->album($useCase->execute($this->requestMapper->fromArray($id, $request->getPayload()->all()))));
    }

    #[Route('/{id}', name: 'api_admin_photo_album_delete', methods: ['DELETE'])]
    public function delete(string $id, DeletePhotoAlbumUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PhotosEdit->value);
        $useCase->execute($id);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
