<?php

namespace App\UI\Content\Page\Http;

use App\Logic\Content\Page\Query\GetNavigationQuery;
use App\Logic\Content\Page\Query\GetPublishedPageByIdQuery;
use App\Logic\Content\Page\Query\GetPublishedPageQuery;
use App\UI\Common\Http\ApiResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1')]
readonly class PublicPageController
{
    public function __construct(private ApiResponseFactory $responseFactory)
    {
    }

    #[Route('/pages/{slug}', name: 'api_public_page', requirements: ['slug' => '.+'], methods: ['GET'], priority: -1)]
    public function page(string $slug, GetPublishedPageQuery $query): JsonResponse
    {
        return $this->responseFactory->page($query->execute($slug));
    }

    #[Route('/pages/id/{id}', name: 'api_public_page_by_id', methods: ['GET'])]
    public function pageById(string $id, GetPublishedPageByIdQuery $query): JsonResponse
    {
        return $this->responseFactory->page($query->execute($id));
    }

    #[Route('/navigation', name: 'api_public_navigation', methods: ['GET'])]
    public function navigation(GetNavigationQuery $query): JsonResponse
    {
        return new JsonResponse(['items' => $query->execute()]);
    }
}
