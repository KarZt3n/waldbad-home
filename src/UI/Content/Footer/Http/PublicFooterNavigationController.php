<?php

namespace App\UI\Content\Footer\Http;

use App\Logic\Content\Footer\Dto\FooterLinkResponse;
use App\Logic\Content\Footer\Query\GetPublicFooterLinksQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/footer-navigation')]
readonly class PublicFooterNavigationController
{
    #[Route('', name: 'api_public_footer_navigation', methods: ['GET'])]
    public function links(GetPublicFooterLinksQuery $query): JsonResponse
    {
        return new JsonResponse(['items' => array_map(static fn (FooterLinkResponse $link): array => [
            'label' => $link->label,
            'slug' => $link->slug,
        ], $query->execute())]);
    }
}
