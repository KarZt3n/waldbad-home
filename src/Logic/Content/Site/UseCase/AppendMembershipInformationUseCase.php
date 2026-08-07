<?php

namespace App\Logic\Content\Site\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Content\Page\Exception\PageNotFoundException;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Site\Definition\MembershipInformationDefinition;

readonly class AppendMembershipInformationUseCase
{
    private const PAGE_SLUG = 'mitglied-werden';

    public function __construct(
        private PageManagerInterface $pageManager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(): Page
    {
        $page = $this->findPage();
        foreach ($page->blocks as $block) {
            if ($block->type === ContentBlockType::Heading && $block->content === MembershipInformationDefinition::CONSENT_HEADING) {
                return $page;
            }
        }

        $draft = $page->revise(
            title: $page->title,
            slug: $page->slug,
            navigationLabel: $page->navigationLabel,
            parentId: $page->parentId,
            blocks: [...$page->blocks, ...MembershipInformationDefinition::blocks()],
            visible: $page->visible,
            showInNavigation: $page->showInNavigation,
            navigationPosition: $page->navigationPosition,
            seoTitle: $page->seoTitle,
            seoDescription: $page->seoDescription,
            updatedAt: $this->clock->now(),
        );
        $savedDraft = $this->pageManager->save($draft);

        return $this->pageManager->save($savedDraft->publish($this->clock->now()));
    }

    private function findPage(): Page
    {
        foreach ($this->pageManager->all() as $page) {
            if ($page->slug === self::PAGE_SLUG) {
                return $page;
            }
        }

        throw new PageNotFoundException(self::PAGE_SLUG);
    }
}
