<?php

namespace App\UI\Content\Branding\Http;

use App\Logic\Content\Branding\Model\SiteBranding;
use App\Logic\Content\Branding\Query\GetSiteBrandingQuery;
use App\Logic\Content\Branding\UseCase\UpdateSiteBrandingUseCase;
use App\Logic\IdentityAccess\Authorization\PageAuthorizationService;
use App\UI\IdentityAccess\Security\AuthenticatedUser;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** „Seiten“ → „Logo“: Logo in der Kopfzeile aller öffentlichen Seiten. */
#[Route('/api/admin/v1/site-branding')]
class AdminSiteBrandingController extends AbstractController
{
    public function __construct(private readonly PageAuthorizationService $authorization)
    {
    }

    #[Route('', name: 'api_admin_site_branding_get', methods: ['GET'])]
    public function get(GetSiteBrandingQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PagesView->value);

        return $this->branding($query);
    }

    #[Route('', name: 'api_admin_site_branding_update', methods: ['PUT'])]
    public function update(Request $request, UpdateSiteBrandingUseCase $useCase, GetSiteBrandingQuery $query, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PagesView->value);
        $this->authorization->assertCanEditBranding($user->pageAuthorizationContext());
        $logoUrl = $request->getPayload()->all()['logoUrl'] ?? null;
        if ($logoUrl !== null && !is_string($logoUrl)) {
            throw new BadRequestHttpException('„logoUrl“ muss eine Zeichenkette oder null sein.');
        }
        if ($logoUrl !== null && mb_strlen($logoUrl) > SiteBranding::MAX_URL_LENGTH) {
            throw new BadRequestHttpException('Die Logo-Adresse ist zu lang.');
        }
        $useCase->execute($logoUrl);

        return $this->branding($query);
    }

    private function branding(GetSiteBrandingQuery $query): JsonResponse
    {
        $response = $query->execute();

        return new JsonResponse([
            'logoUrl' => $response->logoUrl,
            'customLogoUrl' => $response->customLogoUrl,
            'defaultLogoUrl' => $response->defaultLogoUrl,
        ]);
    }
}
