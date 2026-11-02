<?php

namespace App\UI\Media\PhotoAlbum\Http;

use App\Logic\Media\PhotoAlbum\Query\GetPhotoAlbumYearQuery;
use App\Logic\Media\PhotoAlbum\Query\ListPhotoAlbumYearsQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/photo-albums')]
readonly class PublicPhotoAlbumController
{
    public function __construct(private PhotoAlbumResponseFactory $responseFactory)
    {
    }

    #[Route('/years', name: 'api_public_photo_album_years', methods: ['GET'])]
    public function years(ListPhotoAlbumYearsQuery $query): JsonResponse
    {
        return new JsonResponse($this->responseFactory->years($query->execute()));
    }

    #[Route('/years/{year}', name: 'api_public_photo_album_year', requirements: ['year' => '\d{4}'], methods: ['GET'])]
    public function year(int $year, GetPhotoAlbumYearQuery $query): JsonResponse
    {
        return new JsonResponse($this->responseFactory->year($query->execute($year)));
    }
}
