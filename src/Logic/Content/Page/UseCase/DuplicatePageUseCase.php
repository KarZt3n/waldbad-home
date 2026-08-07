<?php

namespace App\Logic\Content\Page\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;

readonly class DuplicatePageUseCase
{
    public function __construct(
        private PageManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id): PageResponse
    {
        $source = $this->manager->get($id);
        $now = $this->clock->now();
        $title = $source->title.' (Kopie)';
        $page = new Page(
            id: $this->identifierGenerator->generate(),
            title: $title,
            slug: $this->manager->nextAvailableSlug($source->slug.'-kopie'),
            navigationLabel: $source->navigationLabel.' (Kopie)',
            parentId: $source->parentId,
            blocks: $source->blocks,
            status: PageStatus::Draft,
            visible: false,
            showInNavigation: false,
            navigationPosition: $source->navigationPosition + 1,
            seoTitle: $title,
            seoDescription: $source->seoDescription,
            version: 0,
            createdAt: $now,
            updatedAt: $now,
            publishedAt: null,
        );

        return PageResponse::fromPage($this->manager->save($page));
    }
}
