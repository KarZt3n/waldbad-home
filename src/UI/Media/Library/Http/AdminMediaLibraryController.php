<?php

namespace App\UI\Media\Library\Http;

use App\Logic\Media\Library\Dto\CreateMediaFolderRequest;
use App\Logic\Media\Library\Dto\ListMediaLibraryRequest;
use App\Logic\Media\Library\Dto\MediaLibraryItemResponse;
use App\Logic\Media\Library\Dto\MoveMediaFileRequest;
use App\Logic\Media\Library\Dto\RenameMediaFileRequest;
use App\Logic\Media\Library\Dto\ReplaceMediaFileRequest;
use App\Logic\Media\Library\Dto\UploadMediaFileRequest;
use App\Logic\Media\Library\Model\MediaFile;
use App\Logic\Media\Library\Model\MediaFolder;
use App\Logic\Media\Library\Model\MediaUsage;
use App\Logic\Media\Library\Query\ListMediaFoldersQuery;
use App\Logic\Media\Library\Query\ListMediaLibraryQuery;
use App\Logic\Media\Library\UseCase\CreateMediaFolderUseCase;
use App\Logic\Media\Library\UseCase\DeleteMediaFileUseCase;
use App\Logic\Media\Library\UseCase\DeleteMediaFolderUseCase;
use App\Logic\Media\Library\UseCase\MoveMediaFileUseCase;
use App\Logic\Media\Library\UseCase\RenameMediaFileUseCase;
use App\Logic\Media\Library\UseCase\ReplaceMediaFileUseCase;
use App\Logic\Media\Library\UseCase\UploadMediaFileUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/media-library')]
final class AdminMediaLibraryController extends AbstractController
{
    public function __construct(private readonly MediaUploadRequestMapper $requestMapper)
    {
    }

    #[Route('', name: 'api_admin_media_library_list', methods: ['GET'])]
    public function list(Request $request, ListMediaLibraryQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MediaLibraryView->value);
        $items = $query->execute(new ListMediaLibraryRequest(
            folderPath: $this->requestMapper->folderPath($request->query->get('folder')),
            recursive: $request->query->getBoolean('recursive'),
        ));

        return new JsonResponse(['items' => array_map($this->item(...), $items)]);
    }

    #[Route('', name: 'api_admin_media_library_upload', methods: ['POST'])]
    public function upload(Request $request, UploadMediaFileUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MediaLibraryEdit->value);
        $file = $useCase->execute(new UploadMediaFileRequest(
            folderPath: $this->requestMapper->folderPath($request->request->get('folder')),
            upload: $this->requestMapper->upload($request),
        ));

        return new JsonResponse($this->fileResponse($file), JsonResponse::HTTP_CREATED);
    }

    #[Route('/replace', name: 'api_admin_media_library_replace', methods: ['POST'])]
    public function replace(Request $request, ReplaceMediaFileUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MediaLibraryEdit->value);
        $response = $useCase->execute(new ReplaceMediaFileRequest(
            url: $this->requestMapper->url($request->request->get('url')),
            upload: $this->requestMapper->upload($request),
        ));

        return new JsonResponse([
            'file' => $this->fileResponse($response->file),
            'updatedReferences' => $response->updatedReferences,
        ]);
    }

    #[Route('/move', name: 'api_admin_media_library_move', methods: ['POST'])]
    public function move(Request $request, MoveMediaFileUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MediaLibraryEdit->value);
        $data = $request->getPayload();
        $response = $useCase->execute(new MoveMediaFileRequest(
            url: $this->requestMapper->url($data->get('url')),
            targetFolderPath: $this->requestMapper->folderPath($data->get('targetFolder')),
        ));

        return new JsonResponse([
            'file' => $this->fileResponse($response->file),
            'updatedReferences' => $response->updatedReferences,
        ]);
    }

    #[Route('/rename', name: 'api_admin_media_library_rename', methods: ['POST'])]
    public function rename(Request $request, RenameMediaFileUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MediaLibraryEdit->value);
        $data = $request->getPayload();
        $response = $useCase->execute(new RenameMediaFileRequest(
            url: $this->requestMapper->url($data->get('url')),
            filename: $this->requestMapper->filename($data->get('filename')),
        ));

        return new JsonResponse([
            'file' => $this->fileResponse($response->file),
            'updatedReferences' => $response->updatedReferences,
        ]);
    }

    #[Route('', name: 'api_admin_media_library_delete', methods: ['DELETE'])]
    public function delete(Request $request, DeleteMediaFileUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MediaLibraryEdit->value);
        $response = $useCase->execute($this->requestMapper->url($request->getPayload()->get('url')));

        return new JsonResponse(['removedReferences' => $response->removedReferences]);
    }

    #[Route('/folders', name: 'api_admin_media_library_folders', methods: ['GET'])]
    public function folders(ListMediaFoldersQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MediaLibraryView->value);

        return new JsonResponse(['items' => array_map($this->folder(...), $query->execute())]);
    }

    #[Route('/folders', name: 'api_admin_media_library_folder_create', methods: ['POST'])]
    public function createFolder(Request $request, CreateMediaFolderUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MediaLibraryEdit->value);
        $data = $request->getPayload();
        $name = trim($data->getString('name'));
        if ($name === '') {
            throw new BadRequestHttpException('Der Ordnername ist erforderlich.');
        }
        $folder = $useCase->execute(new CreateMediaFolderRequest(
            parentPath: $this->requestMapper->folderPath($data->get('parentPath')),
            name: $name,
        ));

        return new JsonResponse($this->folder($folder), JsonResponse::HTTP_CREATED);
    }

    #[Route('/folders', name: 'api_admin_media_library_folder_delete', methods: ['DELETE'])]
    public function deleteFolder(Request $request, DeleteMediaFolderUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MediaLibraryEdit->value);
        $useCase->execute($this->requestMapper->folderPath($request->getPayload()->get('path')));

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @return array{path: string, name: string, parentPath: string, fileCount: int}
     */
    private function folder(MediaFolder $folder): array
    {
        return [
            'path' => $folder->path,
            'name' => $folder->name(),
            'parentPath' => $folder->parentPath(),
            'fileCount' => $folder->fileCount,
        ];
    }

    /**
     * @return array{
     *     url: string, folder: string, originalName: string, mimeType: string, isImage: bool, size: int,
     *     width: int|null, height: int|null, source: string|null, modifiedAt: string
     * }
     */
    private function fileResponse(MediaFile $file): array
    {
        return [
            'url' => $file->url,
            'folder' => $file->folderPath,
            'originalName' => $file->originalName,
            'mimeType' => $file->mimeType,
            'isImage' => $file->isImage(),
            'size' => $file->size,
            'width' => $file->width,
            'height' => $file->height,
            'source' => $file->source,
            'modifiedAt' => $file->modifiedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{
     *     url: string, folder: string, originalName: string, mimeType: string, isImage: bool, size: int,
     *     width: int|null, height: int|null, source: string|null, modifiedAt: string,
     *     usages: list<array{area: string, referenceId: string, title: string, detail: string|null}>
     * }
     */
    private function item(MediaLibraryItemResponse $item): array
    {
        return $this->fileResponse($item->file) + [
            'usages' => array_map(
                static fn (MediaUsage $usage): array => [
                    'area' => $usage->area->value,
                    'referenceId' => $usage->referenceId,
                    'title' => $usage->title,
                    'detail' => $usage->detail,
                ],
                $item->usages,
            ),
        ];
    }
}
