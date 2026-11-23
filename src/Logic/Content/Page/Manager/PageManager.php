<?php

namespace App\Logic\Content\Page\Manager;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\ModelCacheInterface;
use App\Logic\Content\Page\Exception\PageNotFoundException;
use App\Logic\Content\Page\Exception\PageSlugAlreadyExistsException;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\PageProcessorInterface;
use App\Logic\Content\Page\PageProviderInterface;

/**
 * Die veröffentlichten Fassungen werden für öffentliche Anfragen (Seite, Navigation, Footer,
 * Sitemap) serverseitig gecacht und nach jedem Schreiben verworfen; Entwürfe der Redaktion
 * kommen immer direkt aus der Datenbank.
 */
readonly class PageManager implements PageManagerInterface
{
    private const string PUBLISHED_CACHE_KEY = 'pages.published';

    public function __construct(
        private PageProviderInterface $provider,
        private PageProcessorInterface $processor,
        private ModelCacheInterface $cache,
    ) {
    }

    public function get(string $id): Page
    {
        return $this->provider->findById($id) ?? throw new PageNotFoundException($id);
    }

    public function getPublished(string $slug): Page
    {
        foreach ($this->publishedPages() as $page) {
            if ($page->visible && $page->slug === $slug) {
                return $page;
            }
        }

        throw new PageNotFoundException($slug);
    }

    public function getPublishedById(string $id): Page
    {
        foreach ($this->publishedPages() as $page) {
            if ($page->visible && $page->id === $id) {
                return $page;
            }
        }

        throw new PageNotFoundException($id);
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function navigation(): array
    {
        $pages = array_values(array_filter($this->publishedPages(), static fn (Page $page): bool => $page->visible && $page->showInNavigation));
        usort($pages, static fn (Page $left, Page $right): int => $left->navigationPosition <=> $right->navigationPosition);

        return $pages;
    }

    public function publishedPages(): array
    {
        $cached = $this->cachedPublishedPages();
        if ($cached !== null) {
            return $cached;
        }
        $pages = $this->provider->findAllPublished();
        $this->cache->store(self::PUBLISHED_CACHE_KEY, $pages);

        return $pages;
    }

    /** @return list<Page>|null `null` bei leerem oder unerwartetem Cache-Inhalt */
    private function cachedPublishedPages(): ?array
    {
        $cached = $this->cache->load(self::PUBLISHED_CACHE_KEY);
        if (!is_array($cached) || !array_is_list($cached)) {
            return null;
        }
        $pages = [];
        foreach ($cached as $page) {
            if (!$page instanceof Page) {
                return null;
            }
            $pages[] = $page;
        }

        return $pages;
    }

    public function ensureSlugAvailable(string $slug, ?string $exceptId = null): void
    {
        if ($this->provider->slugExists($slug, $exceptId)) {
            throw new PageSlugAlreadyExistsException($slug);
        }
    }

    public function hierarchicalSlug(string $slug, ?string $parentId): string
    {
        $segments = explode('/', $slug);
        $leafSlug = end($segments);
        if ($leafSlug === '') {
            return $slug;
        }

        if ($parentId === null) {
            return $leafSlug;
        }

        return $this->get($parentId)->slug.'/'.$leafSlug;
    }

    public function ensureParentAllowed(?string $parentId, ?string $pageId = null): void
    {
        if ($parentId === null) {
            return;
        }
        if ($parentId === $pageId) {
            throw new BusinessRuleViolationException('Eine Seite kann nicht ihre eigene Unterseite sein.');
        }

        $visited = [];
        $ancestor = $this->get($parentId);
        while (true) {
            if ($ancestor->id === $pageId) {
                throw new BusinessRuleViolationException('Die gewählte Elternseite würde einen Kreislauf erzeugen.');
            }
            if (isset($visited[$ancestor->id])) {
                throw new BusinessRuleViolationException('Die Seitenstruktur enthält einen Kreislauf.');
            }
            $visited[$ancestor->id] = true;
            if ($ancestor->parentId === null) {
                return;
            }
            $ancestor = $this->get($ancestor->parentId);
        }
    }

    public function ensureEmbeddedPagesAllowed(array $blocks, string $pageId): void
    {
        foreach ($blocks as $block) {
            if (in_array($block->type, [ContentBlockType::EmbeddedPage, ContentBlockType::PageTeaser], true)
                && $block->embeddedPageId !== null) {
                $this->validateEmbeddedPage($block->embeddedPageId, $pageId, []);
            }
        }
    }

    public function nextAvailableSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 2;
        while ($this->provider->slugExists($slug)) {
            $slug = $baseSlug.'-'.$suffix;
            ++$suffix;
        }

        return $slug;
    }

    public function ensureCanDelete(Page $page): void
    {
        if ($page->slug === 'startseite') {
            throw new BusinessRuleViolationException('Die Startseite kann nicht gelöscht werden.');
        }

        foreach ([...$this->all(), ...$this->publishedPages()] as $candidate) {
            if ($candidate->parentId === $page->id) {
                throw new BusinessRuleViolationException('Die Seite besitzt Unterseiten. Verschiebe oder lösche diese zuerst.');
            }
            foreach ($candidate->blocks as $block) {
                if (in_array($block->type, [ContentBlockType::EmbeddedPage, ContentBlockType::PageTeaser], true)
                    && $block->embeddedPageId === $page->id) {
                    throw new BusinessRuleViolationException(sprintf(
                        'Die Seite wird in „%s“ eingebettet oder als Teaser verlinkt. Entferne dort zuerst den entsprechenden Inhaltsblock.',
                        $candidate->title,
                    ));
                }
            }
        }
    }

    /**
     * @param array<string, true> $visited
     */
    private function validateEmbeddedPage(string $embeddedPageId, string $pageId, array $visited): void
    {
        if ($embeddedPageId === $pageId) {
            throw new BusinessRuleViolationException('Eine Seite kann nicht in sich selbst eingebettet werden.');
        }
        if (isset($visited[$embeddedPageId])) {
            throw new BusinessRuleViolationException('Die eingebetteten Seiten würden einen Kreislauf erzeugen.');
        }

        $visited[$embeddedPageId] = true;
        $embeddedPage = $this->get($embeddedPageId);
        foreach ($embeddedPage->blocks as $block) {
            if ($block->type === ContentBlockType::EmbeddedPage && $block->embeddedPageId !== null) {
                $this->validateEmbeddedPage($block->embeddedPageId, $pageId, $visited);
            }
        }
    }

    public function save(Page $page): Page
    {
        $saved = $this->processor->save($page);
        $this->cache->invalidate(self::PUBLISHED_CACHE_KEY);

        return $saved;
    }

    public function saveAll(array $pages): array
    {
        $saved = $this->processor->saveAll($pages);
        $this->cache->invalidate(self::PUBLISHED_CACHE_KEY);

        return $saved;
    }

    public function savePublishedBlocks(Page $publishedPage): void
    {
        $this->processor->savePublishedBlocks($publishedPage);
        $this->cache->invalidate(self::PUBLISHED_CACHE_KEY);
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
        $this->cache->invalidate(self::PUBLISHED_CACHE_KEY);
    }
}
