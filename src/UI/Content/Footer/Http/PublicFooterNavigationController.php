<?php

namespace App\UI\Content\Footer\Http;

use App\UI\Site\Http\PublicSiteChromeResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/footer-navigation')]
readonly class PublicFooterNavigationController
{
    #[Route('', name: 'api_public_footer_navigation', methods: ['GET'])]
    public function links(PublicSiteChromeResponseFactory $chrome): JsonResponse
    {
        return new JsonResponse(['items' => $chrome->footerLinks()]);
    }
}
