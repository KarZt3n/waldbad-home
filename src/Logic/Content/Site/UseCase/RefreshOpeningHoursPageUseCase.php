<?php

namespace App\Logic\Content\Site\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Content\Page\Exception\PageNotFoundException;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Site\Definition\OpeningHoursPageDefinition;

readonly class RefreshOpeningHoursPageUseCase
{
    public function __construct(
        private PageManagerInterface $pageManager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(): Page
    {
        $page = $this->findPage();
        $draft = $page->revise(
            title: OpeningHoursPageDefinition::TITLE,
            slug: OpeningHoursPageDefinition::SLUG,
            navigationLabel: OpeningHoursPageDefinition::NAVIGATION_LABEL,
            parentId: $page->parentId,
            blocks: OpeningHoursPageDefinition::blocks(),
            visible: true,
            showInNavigation: true,
            navigationPosition: $page->navigationPosition,
            seoTitle: OpeningHoursPageDefinition::TITLE,
            seoDescription: OpeningHoursPageDefinition::SEO_DESCRIPTION,
            updatedAt: $this->clock->now(),
        );
        $savedDraft = $this->pageManager->save($draft);

        return $this->pageManager->save($savedDraft->publish($this->clock->now()));
    }

    private function findPage(): Page
    {
        foreach ($this->pageManager->all() as $page) {
            if ($page->slug === OpeningHoursPageDefinition::SLUG) {
                return $page;
            }
        }

        throw new PageNotFoundException(OpeningHoursPageDefinition::SLUG);
    }
}
