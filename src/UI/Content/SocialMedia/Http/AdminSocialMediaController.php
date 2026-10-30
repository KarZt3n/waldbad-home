<?php

namespace App\UI\Content\SocialMedia\Http;

use App\Logic\Content\SocialMedia\Dto\SocialMediaLinkRequest;
use App\Logic\Content\SocialMedia\Dto\SocialMediaLinkResponse;
use App\Logic\Content\SocialMedia\Dto\UpdateSocialMediaSettingsRequest;
use App\Logic\Content\SocialMedia\Model\SocialMediaLink;
use App\Logic\Content\SocialMedia\Model\SocialPlatform;
use App\Logic\Content\SocialMedia\Query\GetSocialMediaSettingsQuery;
use App\Logic\Content\SocialMedia\UseCase\UpdateSocialMediaSettingsUseCase;
use App\Logic\IdentityAccess\Authorization\PageAuthorizationService;
use App\UI\IdentityAccess\Security\AuthenticatedUser;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** „Seiten“ → „Social Media“: Sichtbarkeit und Links der Social-Media-Leiste auf allen Seiten. */
#[Route('/api/admin/v1/social-media')]
class AdminSocialMediaController extends AbstractController
{
    public function __construct(private readonly PageAuthorizationService $authorization)
    {
    }

    #[Route('', name: 'api_admin_social_media_get', methods: ['GET'])]
    public function get(GetSocialMediaSettingsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PagesView->value);

        return $this->settings($query);
    }

    #[Route('', name: 'api_admin_social_media_update', methods: ['PUT'])]
    public function update(Request $request, UpdateSocialMediaSettingsUseCase $useCase, GetSocialMediaSettingsQuery $query, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PagesView->value);
        $this->authorization->assertCanEditSocialMedia($user->pageAuthorizationContext());
        $useCase->execute($this->updateRequest($request));

        return $this->settings($query);
    }

    private function updateRequest(Request $request): UpdateSocialMediaSettingsRequest
    {
        $payload = $request->getPayload();
        $visible = $payload->all()['visible'] ?? null;
        if (!is_bool($visible)) {
            throw new BadRequestHttpException('„visible“ muss ein Wahrheitswert sein.');
        }
        $links = [];
        foreach ($payload->all('links') as $link) {
            if (!is_array($link) || !is_string($link['platform'] ?? null) || !is_string($link['url'] ?? null)) {
                throw new BadRequestHttpException('Jeder Link braucht eine Plattform und eine Adresse.');
            }
            if (mb_strlen($link['url']) > SocialMediaLink::MAX_URL_LENGTH) {
                throw new BadRequestHttpException('Der Link ist zu lang.');
            }
            $platform = SocialPlatform::tryFrom($link['platform']) ?? throw new BadRequestHttpException('Unbekannte Social-Media-Plattform.');
            $links[] = new SocialMediaLinkRequest($platform, $link['url']);
        }

        return new UpdateSocialMediaSettingsRequest(visible: $visible, links: $links);
    }

    private function settings(GetSocialMediaSettingsQuery $query): JsonResponse
    {
        $response = $query->execute();

        return new JsonResponse([
            'visible' => $response->visible,
            'links' => array_map(static fn (SocialMediaLinkResponse $link): array => [
                'platform' => $link->platform,
                'label' => $link->label,
                'url' => $link->url,
            ], $response->links),
            'platforms' => array_map(
                static fn (SocialPlatform $platform): array => ['key' => $platform->value, 'label' => $platform->label()],
                SocialPlatform::cases(),
            ),
        ]);
    }
}
