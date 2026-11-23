<?php

namespace App\Logic\Content\Footer\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\Page\Model\Page;

/**
 * Seiten im Footer aller öffentlichen Seiten, in Anzeigereihenfolge. Der Link „Redaktion“ ist
 * keine CMS-Seite und steht immer fest am Ende (siehe `buildSiteFooter`).
 */
readonly class FooterNavigation
{
    public const int MAX_PAGES = 20;
    /** Vorbelegung, solange der Footer noch nie gespeichert wurde – entspricht dem früher fest eingebauten Footer. */
    private const array DEFAULT_PAGE_SLUGS = ['impressum', 'kontakt', 'gaestebuch', 'unterstuetzer'];

    /**
     * @param list<string> $pageIds
     */
    public function __construct(public array $pageIds)
    {
        if (count($pageIds) > self::MAX_PAGES) {
            throw new BusinessRuleViolationException(sprintf('Im Footer sind höchstens %d Seiten möglich.', self::MAX_PAGES));
        }
        if (count(array_unique($pageIds)) !== count($pageIds)) {
            throw new BusinessRuleViolationException('Eine Seite ist mehrfach im Footer eingetragen.');
        }
    }

    /**
     * @param list<Page> $pages
     */
    public static function defaults(array $pages): self
    {
        $pageIds = [];
        foreach (self::DEFAULT_PAGE_SLUGS as $slug) {
            foreach ($pages as $page) {
                if ($page->slug === $slug) {
                    $pageIds[] = $page->id;
                    break;
                }
            }
        }

        return new self($pageIds);
    }

    /**
     * Ordnet `$pages` in Footer-Reihenfolge; nicht (mehr) vorhandene Seiten entfallen.
     *
     * @param list<Page> $pages
     * @return list<Page>
     */
    public function pagesIn(array $pages): array
    {
        $byId = [];
        foreach ($pages as $page) {
            $byId[$page->id] = $page;
        }
        $ordered = [];
        foreach ($this->pageIds as $pageId) {
            if (isset($byId[$pageId])) {
                $ordered[] = $byId[$pageId];
            }
        }

        return $ordered;
    }
}
