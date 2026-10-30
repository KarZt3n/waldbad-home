<?php

namespace App\UI\Content\SocialMedia\Http;

use App\Logic\Content\SocialMedia\Dto\SocialMediaLinkResponse;
use App\Logic\Content\SocialMedia\Query\GetPublicSocialMediaLinksQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/social-media')]
readonly class PublicSocialMediaController
{
    #[Route('', name: 'api_public_social_media', methods: ['GET'])]
    public function links(GetPublicSocialMediaLinksQuery $query): JsonResponse
    {
        return new JsonResponse(['items' => array_map(static fn (SocialMediaLinkResponse $link): array => [
            'platform' => $link->platform,
            'label' => $link->label,
            'url' => $link->url,
        ], $query->execute())]);
    }
}
