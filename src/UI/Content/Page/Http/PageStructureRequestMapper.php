<?php

namespace App\UI\Content\Page\Http;

use App\Logic\Content\Page\Dto\ReorderPageRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class PageStructureRequestMapper
{
    public function reorder(string $id, Request $request): ReorderPageRequest
    {
        $data = $request->getPayload();
        $parentId = $data->get('parentId');
        if ($parentId !== null && !is_string($parentId)) {
            throw new BadRequestHttpException('Die Elternseite muss als ID oder null angegeben werden.');
        }
        $navigationPosition = $data->get('navigationPosition');
        $version = $data->get('version');
        if (!is_int($navigationPosition) || $navigationPosition < 0) {
            throw new BadRequestHttpException('Die Navigationsposition muss eine nicht-negative ganze Zahl sein.');
        }
        if (!is_int($version) || $version < 1) {
            throw new BadRequestHttpException('Die Seitenversion muss eine positive ganze Zahl sein.');
        }

        return new ReorderPageRequest(
            id: $id,
            parentId: $parentId === null || trim($parentId) === '' ? null : trim($parentId),
            navigationPosition: $navigationPosition,
            expectedVersion: $version,
        );
    }
}
