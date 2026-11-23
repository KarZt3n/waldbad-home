<?php

namespace App\UI\Content\Footer\Http;

use App\Logic\Content\Footer\Dto\FooterPageResponse;
use App\Logic\Content\Footer\Model\FooterNavigation;
use App\Logic\Content\Footer\Query\GetFooterNavigationQuery;
use App\Logic\Content\Footer\UseCase\UpdateFooterNavigationUseCase;
use App\Logic\IdentityAccess\Authorization\PageAuthorizationService;
use App\UI\IdentityAccess\Security\AuthenticatedUser;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** „Seiten“ → „Footer“: welche Seiten in welcher Reihenfolge im Footer aller Seiten stehen. */
#[Route('/api/admin/v1/footer-navigation')]
class AdminFooterNavigationController extends AbstractController
{
    public function __construct(private readonly PageAuthorizationService $authorization)
    {
    }

    #[Route('', name: 'api_admin_footer_navigation_get', methods: ['GET'])]
    public function get(GetFooterNavigationQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PagesView->value);

        return $this->navigation($query);
    }

    #[Route('', name: 'api_admin_footer_navigation_update', methods: ['PUT'])]
    public function update(Request $request, UpdateFooterNavigationUseCase $useCase, GetFooterNavigationQuery $query, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PagesView->value);
        $this->authorization->assertCanEditFooterNavigation($user->pageAuthorizationContext());
        $useCase->execute($this->pageIds($request));

        return $this->navigation($query);
    }

    /** @return list<string> */
    private function pageIds(Request $request): array
    {
        $rawPageIds = $request->getPayload()->all('pageIds');
        if (!array_is_list($rawPageIds) || count($rawPageIds) > FooterNavigation::MAX_PAGES) {
            throw new BadRequestHttpException(sprintf('„pageIds“ muss eine Liste mit höchstens %d Seiten sein.', FooterNavigation::MAX_PAGES));
        }
        $pageIds = [];
        foreach ($rawPageIds as $rawPageId) {
            if (!is_string($rawPageId) || trim($rawPageId) === '') {
                throw new BadRequestHttpException('Jede Seite im Footer braucht eine gültige Kennung.');
            }
            $pageIds[] = trim($rawPageId);
        }

        return $pageIds;
    }

    private function navigation(GetFooterNavigationQuery $query): JsonResponse
    {
        $response = $query->execute();
        $page = static fn (FooterPageResponse $page): array => [
            'id' => $page->id,
            'label' => $page->label,
            'slug' => $page->slug,
            'online' => $page->online,
        ];

        return new JsonResponse([
            'pages' => array_map($page, $response->pages),
            'available' => array_map($page, $response->available),
        ]);
    }
}
