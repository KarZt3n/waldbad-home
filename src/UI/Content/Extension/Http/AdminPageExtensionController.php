<?php

namespace App\UI\Content\Extension\Http;

use App\Logic\Content\Extension\Dto\PageExtensionResponse;
use App\Logic\Content\Extension\Dto\PageExtensionTextFieldResponse;
use App\Logic\Content\Extension\Model\PageExtension;
use App\Logic\Content\Extension\Query\ListPageExtensionsQuery;
use App\Logic\Content\Extension\UseCase\ResetPageExtensionTextsUseCase;
use App\Logic\Content\Extension\UseCase\UpdatePageExtensionTextsUseCase;
use App\Logic\IdentityAccess\Authorization\PageAuthorizationService;
use App\UI\IdentityAccess\Security\AuthenticatedUser;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** „Seiten“ → „Erweiterungen“: Übersicht der Seitenerweiterungen und Pflege ihrer Texte. */
#[Route('/api/admin/v1/page-extensions')]
class AdminPageExtensionController extends AbstractController
{
    public function __construct(private readonly PageAuthorizationService $authorization)
    {
    }

    #[Route('', name: 'api_admin_page_extensions_list', methods: ['GET'])]
    public function list(ListPageExtensionsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PagesView->value);

        return $this->items($query);
    }

    #[Route('/{key}', name: 'api_admin_page_extensions_update', methods: ['PUT'])]
    public function update(string $key, Request $request, UpdatePageExtensionTextsUseCase $useCase, ListPageExtensionsQuery $query, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PagesView->value);
        $this->authorization->assertCanEditExtensionTexts($user->pageAuthorizationContext());
        $useCase->execute($this->resolveExtension($key), $this->texts($request));

        return $this->items($query);
    }

    #[Route('/{key}/reset', name: 'api_admin_page_extensions_reset', methods: ['POST'])]
    public function reset(string $key, ResetPageExtensionTextsUseCase $useCase, ListPageExtensionsQuery $query, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::PagesView->value);
        $this->authorization->assertCanEditExtensionTexts($user->pageAuthorizationContext());
        $useCase->execute($this->resolveExtension($key));

        return $this->items($query);
    }

    private function items(ListPageExtensionsQuery $query): JsonResponse
    {
        return new JsonResponse(['items' => array_map($this->toArray(...), $query->execute())]);
    }

    private function resolveExtension(string $key): PageExtension
    {
        return PageExtension::tryFrom($key) ?? throw new BadRequestHttpException('Unbekannte Seitenerweiterung.');
    }

    /**
     * @return array<string, string>
     */
    private function texts(Request $request): array
    {
        $texts = [];
        foreach ($request->getPayload()->all('texts') as $key => $text) {
            if (!is_string($key) || !is_string($text)) {
                throw new BadRequestHttpException('Die Texte müssen als Zuordnung von Feld zu Text übermittelt werden.');
            }
            $texts[$key] = $text;
        }

        return $texts;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(PageExtensionResponse $response): array
    {
        return [
            'key' => $response->key,
            'label' => $response->label,
            'description' => $response->description,
            'fields' => array_map(static fn (PageExtensionTextFieldResponse $field): array => [
                'key' => $field->key,
                'label' => $field->label,
                'text' => $field->text,
                'defaultText' => $field->defaultText,
                'multiline' => $field->multiline,
                'consent' => $field->consent,
                'isDefault' => $field->isDefault,
            ], $response->fields),
        ];
    }
}
