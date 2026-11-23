<?php

namespace App\UI\Content\SocialMedia\Http;

use App\UI\Site\Http\PublicSiteChromeResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/social-media')]
readonly class PublicSocialMediaController
{
    #[Route('', name: 'api_public_social_media', methods: ['GET'])]
    public function links(PublicSiteChromeResponseFactory $chrome): JsonResponse
    {
        return new JsonResponse(['items' => $chrome->socialMediaLinks()]);
    }
}
