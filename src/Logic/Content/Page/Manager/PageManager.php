<?php

namespace App\Logic\Content\Page\Manager;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\Page\Exception\PageNotFoundException;
use App\Logic\Content\Page\Exception\PageSlugAlreadyExistsException;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\PageProcessorInterface;
use App\Logic\Content\Page\PageProviderInterface;

readonly class PageManager implements PageManagerInterface
{
    public function __construct(
        private PageProviderInterface $provider,
        private PageProcessorInterface $processor,
    ) {
    }

    public function get(string $id): Page
    {
        return $this->provider->findById($id) ?? throw new PageNotFoundException($id);
    }

    public function getPublished(string $slug): Page
    {
        return $this->provider->findPublishedBySlug($slug) ?? throw new PageNotFoundException($slug);
    }

    public function getPublishedById(string $id): Page
    {
        return $this->provider->findPublishedById($id) ?? throw new PageNotFoundException($id);
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function navigation(): array
    {
        return $this->provider->findPublishedNavigation();
    }

    public function publishedPages(): array
    {
        return $this->provider->findAllPublished();
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
        return $this->processor->save($page);
    }

    public function saveAll(array $pages): array
    {
        return $this->processor->saveAll($pages);
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
    }
}
