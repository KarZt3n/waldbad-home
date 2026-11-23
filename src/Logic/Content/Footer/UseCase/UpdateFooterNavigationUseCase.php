<?php

namespace App\Logic\Content\Footer\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\Footer\Manager\FooterNavigationManagerInterface;
use App\Logic\Content\Footer\Model\FooterNavigation;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\Page;

/** Wirkt wie die Social-Media-Leiste ohne Freigabe sofort auf allen Seiten. */
readonly class UpdateFooterNavigationUseCase
{
    public function __construct(
        private FooterNavigationManagerInterface $manager,
        private PageManagerInterface $pageManager,
    ) {
    }

    /**
     * @param list<string> $pageIds in Anzeigereihenfolge
     */
    public function execute(array $pageIds): void
    {
        $navigation = new FooterNavigation($pageIds);
        $existingIds = array_map(static fn (Page $page): string => $page->id, $this->pageManager->all());
        foreach ($navigation->pageIds as $pageId) {
            if (!in_array($pageId, $existingIds, true)) {
                throw new BusinessRuleViolationException('Eine ausgewählte Seite existiert nicht mehr.');
            }
        }
        $this->manager->save($navigation);
    }
}
