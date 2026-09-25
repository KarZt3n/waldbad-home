<?php

namespace App\UI\Media\PhotoAlbum\Http;

use App\Logic\Media\PhotoAlbum\Query\ListPhotoAlbumsQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/photo-albums')]
readonly class PublicPhotoAlbumController
{
    public function __construct(private PhotoAlbumResponseFactory $responseFactory)
    {
    }

    #[Route('', name: 'api_public_photo_album_list', methods: ['GET'])]
    public function list(ListPhotoAlbumsQuery $query): JsonResponse
    {
        return new JsonResponse($this->responseFactory->collection($query->execute(onlyVisible: true)));
    }
}
