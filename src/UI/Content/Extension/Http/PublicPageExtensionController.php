<?php

namespace App\UI\Content\Extension\Http;

use App\Logic\Content\Extension\Model\PageExtension;
use App\Logic\Content\Extension\Query\GetPageExtensionTextsQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/page-extensions')]
readonly class PublicPageExtensionController
{
    #[Route('/{key}/texts', name: 'api_public_page_extension_texts', methods: ['GET'])]
    public function texts(string $key, GetPageExtensionTextsQuery $query): JsonResponse
    {
        $response = $query->execute(PageExtension::tryFrom($key) ?? throw new NotFoundHttpException('Unbekannte Seitenerweiterung.'));

        return new JsonResponse([
            'key' => $response->key,
            'texts' => $response->texts,
            'consentVersion' => $response->consentVersion,
        ]);
    }
}
