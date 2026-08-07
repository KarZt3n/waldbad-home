<?php

namespace App\Logic\Content\Page\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Content\Page\Dto\CreatePageRequest;
use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\Model\PageStatus;

readonly class PreviewPageUseCase
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function execute(CreatePageRequest $request): PageResponse
    {
        $now = $this->clock->now();

        return new PageResponse(
            id: 'preview',
            title: $request->title,
            slug: $request->slug,
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
    }
}
