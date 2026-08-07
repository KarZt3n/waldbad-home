<?php

namespace App\UI\Media\Image\Http;

use App\Logic\Media\Image\Model\ImageUpload;
use App\Logic\Media\Image\Model\StoredImage;
use App\Logic\Media\Image\Query\ListStoredImagesQuery;
use App\Logic\Media\Image\UseCase\UploadImageUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/media/images')]
final class AdminImageController extends AbstractController
{
    #[Route('', name: 'api_admin_media_images_list', methods: ['GET'])]
    public function list(ListStoredImagesQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CmsRead->value);

        return new JsonResponse([
            'items' => array_map($this->response(...), $query->execute()),
        ]);
    }

    #[Route('', name: 'api_admin_media_image_upload', methods: ['POST'])]
    public function upload(Request $request, UploadImageUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContentEdit->value);
        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new BadRequestHttpException('Bitte wählen Sie eine gültige Bilddatei aus.');
        }

        $size = $file->getSize();
        if ($size === false) {
            throw new BadRequestHttpException('Die Dateigröße konnte nicht ermittelt werden.');
        }

        $image = $useCase->execute(new ImageUpload(
            temporaryPath: $file->getPathname(),
            originalName: $file->getClientOriginalName(),
            size: $size,
        ));

        return new JsonResponse($this->response($image), JsonResponse::HTTP_CREATED);
    }

    /**
     * @return array{url: string, originalName: string, mimeType: string, size: int, width: int, height: int}
     */
    private function response(StoredImage $image): array
    {
        return [
            'url' => $image->url,
            'originalName' => $image->originalName,
            'mimeType' => $image->mimeType,
            'size' => $image->size,
            'width' => $image->width,
            'height' => $image->height,
        ];
    }
}
