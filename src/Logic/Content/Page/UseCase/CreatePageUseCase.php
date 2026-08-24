<?php

namespace App\Logic\Content\Page\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Content\Page\Dto\CreatePageRequest;
use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;

readonly class CreatePageUseCase
{
    public function __construct(
        private PageManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(CreatePageRequest $request): PageResponse
    {
        $this->manager->ensureParentAllowed($request->parentId);
        $slug = $this->manager->hierarchicalSlug($request->slug, $request->parentId);
        $this->manager->ensureSlugAvailable($slug);
        $now = $this->clock->now();
        $pageId = $this->identifierGenerator->generate();
        $this->manager->ensureEmbeddedPagesAllowed($request->blocks, $pageId);

        $page = new Page(
            id: $pageId,
            title: $request->title,
            slug: $slug,
            navigationLabel: $request->navigationLabel,
            parentId: $request->parentId,
            blocks: $request->blocks,
            status: PageStatus::Draft,
            visible: $request->visible,
            showInNavigation: $request->showInNavigation,
            navigationPosition: $request->navigationPosition,
            seoTitle: $request->seoTitle,
            seoDescription: $request->seoDescription,
            version: 0,
            createdAt: $now,
            updatedAt: $now,
            publishedAt: null,
        );

        return PageResponse::fromPage($this->manager->save($page));
    }
}
