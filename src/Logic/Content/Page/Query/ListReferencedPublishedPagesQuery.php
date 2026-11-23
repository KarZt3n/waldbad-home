<?php

namespace App\Logic\Content\Page\Query;

use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\Page;

/**
 * Veröffentlichte, sichtbare Seiten, die zum Anzeigen einer Seite gebraucht werden, damit sie mit
 * der Seite ausgeliefert werden können statt einzeln nachgeladen (und die Seite beim Nachladen
 * nicht springt). Eingebettete Seiten werden mit ihren eigenen Verweisen aufgelöst, bei
 * Veranstaltungsverweisen nur der angezeigte Veranstaltungsblock; Teaser- und Buttonziele brauchen
 * nur Titel und Adresse und werden nicht weiter verfolgt.
 */
readonly class ListReferencedPublishedPagesQuery
{
    public function __construct(private PageManagerInterface $manager)
    {
    }

    /**
     * @return list<PageResponse> ohne die Seite selbst; leer, wenn sie nicht öffentlich ist
     */
    public function execute(string $pageId): array
    {
        $published = [];
        foreach ($this->manager->publishedPages() as $page) {
            if ($page->visible) {
                $published[$page->id] = $page;
            }
        }
        if (!isset($published[$pageId])) {
            return [];
        }

        $referenced = [];
        $expanded = [$pageId => true];
        $pendingBlocks = [$published[$pageId]->blocks];
        while (($blocks = array_shift($pendingBlocks)) !== null) {
            foreach ($blocks as $block) {
                foreach ($block->referencedPageIds() as $id) {
                    if ($id !== $pageId && isset($published[$id])) {
                        $referenced[$id] = $published[$id];
                    }
                }
                $target = $block->embeddedPageId === null ? null : ($published[$block->embeddedPageId] ?? null);
                if ($target === null) {
                    continue;
                }
                if ($block->type === ContentBlockType::EmbeddedPage && !isset($expanded[$target->id])) {
                    $expanded[$target->id] = true;
                    $pendingBlocks[] = $target->blocks;
                }
                if ($block->type === ContentBlockType::EventReference) {
                    $pendingBlocks[] = $this->referencedEvent($target, $block);
                }
            }
        }

        return array_values(array_map(PageResponse::fromPage(...), $referenced));
    }

    /** @return list<ContentBlock> */
    private function referencedEvent(Page $page, ContentBlock $reference): array
    {
        return array_values(array_filter(
            $page->blocks,
            static fn (ContentBlock $block): bool => $block->type === ContentBlockType::Event
                && $block->eventIdentifier === $reference->eventIdentifier,
        ));
    }
}
