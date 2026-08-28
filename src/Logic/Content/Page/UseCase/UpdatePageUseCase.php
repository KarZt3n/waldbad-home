<?php

namespace App\Logic\Content\Page\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\ConcurrencyException;
use App\Logic\Common\Exception\AccessDeniedException;
use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\Dto\UpdatePageRequest;
use App\Logic\Content\Page\Manager\PageManagerInterface;

readonly class UpdatePageUseCase
{
    public function __construct(
        private PageManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(UpdatePageRequest $request, bool $allowStructureChanges = true): PageResponse
    {
        $page = $this->manager->get($request->id);
        if (!$allowStructureChanges && (
            $page->parentId !== $request->parentId
            || $page->navigationPosition !== $request->navigationPosition
        )) {
            throw new AccessDeniedException('Eingeschränkte Seitenzugänge dürfen die Seitenstruktur nicht verändern.');
        }
        if ($page->version !== $request->expectedVersion) {
            throw new ConcurrencyException('Die Seite wurde zwischenzeitlich geändert. Bitte laden Sie die Daten neu.');
        }
        $this->manager->ensureSlugAvailable($request->slug, $request->id);
        $this->manager->ensureParentAllowed($request->parentId, $request->id);
        $this->manager->ensureEmbeddedPagesAllowed($request->blocks, $request->id);

        $page = $page->revise(
            title: $request->title,
            slug: $request->slug,
            navigationLabel: $request->navigationLabel,
            parentId: $request->parentId,
            blocks: $request->blocks,
            visible: $request->visible,
            showInNavigation: $request->showInNavigation,
            navigationPosition: $request->navigationPosition,
            seoTitle: $request->seoTitle,
            seoDescription: $request->seoDescription,
            updatedAt: $this->clock->now(),
        );

        return PageResponse::fromPage($this->manager->save($page));
    }
}
