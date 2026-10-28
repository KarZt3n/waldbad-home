<?php

namespace App\UI\Media\Image\Http;

use App\Logic\Media\Image\Query\ListStoredImagesQuery;
use App\Logic\Media\Image\UseCase\UploadImageUseCase;
use App\Logic\Media\Image\UseCase\UpdateImageSourceUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/media/images')]
final class AdminImageController extends AbstractController
{
    public function __construct(
        private readonly ImageUploadRequestMapper $requestMapper,
        private readonly StoredImageResponseFactory $responseFactory,
    ) {
    }

    #[Route('', name: 'api_admin_media_images_list', methods: ['GET'])]
    public function list(ListStoredImagesQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PagesView->value);

        return new JsonResponse([
            'items' => array_map($this->responseFactory->image(...), $query->execute()),
        ]);
    }

    #[Route('', name: 'api_admin_media_image_upload', methods: ['POST'])]
    public function upload(Request $request, UploadImageUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PagesEdit->value);

        return new JsonResponse(
            $this->responseFactory->image($useCase->execute($this->requestMapper->fromRequest($request))),
            JsonResponse::HTTP_CREATED,
        );
    }

    #[Route('/source', name: 'api_admin_media_image_source_update', methods: ['PATCH'])]
    public function updateSource(Request $request, UpdateImageSourceUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PagesEdit->value);
        $data = $request->getPayload();
        $url = trim($data->getString('url'));
        if ($url === '') {
            throw new BadRequestHttpException('Die Bild-URL ist erforderlich.');
        }

        return new JsonResponse($this->responseFactory->image($useCase->execute($url, $this->requestMapper->source($data->get('source')))));
    }
}
