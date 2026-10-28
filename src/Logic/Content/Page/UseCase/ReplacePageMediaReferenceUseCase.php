<?php

namespace App\Logic\Content\Page\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\Page;

/**
 * Ersetzt bzw. entfernt ein Bild in Entwürfen und veröffentlichten Fassungen. Die veröffentlichte
 * Fassung wird separat angepasst, weil ein überarbeiteter Entwurf sie sonst nicht berührt.
 */
readonly class ReplacePageMediaReferenceUseCase
{
    public function __construct(
        private PageManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return int Anzahl der geänderten Seiten
     */
    public function execute(string $url, ?string $replacementUrl): int
    {
        $now = $this->clock->now();
        $changedPageIds = [];

        $drafts = array_values(array_filter(
            $this->manager->all(),
            static fn (Page $page): bool => in_array($url, $page->mediaUrls(), true),
        ));
        if ($drafts !== []) {
            $this->manager->saveAll(array_map(
                static fn (Page $page): Page => $page->withMediaReplaced($url, $replacementUrl, $now),
                $drafts,
            ));
            foreach ($drafts as $page) {
                $changedPageIds[$page->id] = true;
            }
        }

        foreach ($this->manager->publishedPages() as $publication) {
            if (!in_array($url, $publication->mediaUrls(), true)) {
                continue;
            }
            $this->manager->savePublishedBlocks($publication->withMediaReplaced($url, $replacementUrl, $now));
            $changedPageIds[$publication->id] = true;
        }

        return count($changedPageIds);
    }
}
