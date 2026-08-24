<?php

namespace App\Logic\Content\Page\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\Exception\ConcurrencyException;
use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\Dto\ReorderPageRequest;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\Page;

readonly class ReorderPageUseCase
{
    public function __construct(
        private PageManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ReorderPageRequest $request): PageResponse
    {
        $page = $this->manager->get($request->id);
        if ($page->version !== $request->expectedVersion) {
            throw new ConcurrencyException('Die Seitenstruktur wurde zwischenzeitlich geändert. Bitte laden Sie die Daten neu.');
        }
        if ($request->navigationPosition < 0) {
            throw new BusinessRuleViolationException('Die Navigationsposition darf nicht negativ sein.');
        }
        if ($page->slug === 'startseite' && $request->parentId !== null) {
            throw new BusinessRuleViolationException('Die Startseite muss eine Hauptseite bleiben.');
        }

        $this->manager->ensureParentAllowed($request->parentId, $page->id);
        $allPages = $this->manager->all();
        $now = $this->clock->now();

        if ($page->parentId === $request->parentId) {
            $siblings = $this->siblingsWithout($allPages, $page->parentId, $page->id);
            array_splice($siblings, min($request->navigationPosition, count($siblings)), 0, [$page]);
            $saved = $this->manager->saveAll($this->positioned($siblings, $page->parentId, $now));

            return PageResponse::fromPage($this->findSavedPage($saved, $page->id));
        }

        $oldSiblings = $this->siblingsWithout($allPages, $page->parentId, $page->id);
        $newSiblings = $this->siblingsWithout($allPages, $request->parentId, $page->id);
        array_splice($newSiblings, min($request->navigationPosition, count($newSiblings)), 0, [$page]);
        $changedPages = [
            ...$this->positioned($oldSiblings, $page->parentId, $now),
            ...$this->positioned($newSiblings, $request->parentId, $now),
        ];
        $saved = $this->manager->saveAll($changedPages);

        return PageResponse::fromPage($this->findSavedPage($saved, $page->id));
    }

    /**
     * @param list<Page> $pages
     * @return list<Page>
     */
    private function siblingsWithout(array $pages, ?string $parentId, string $excludedId): array
    {
        $siblings = array_values(array_filter(
            $pages,
            static fn (Page $candidate): bool => $candidate->parentId === $parentId && $candidate->id !== $excludedId,
        ));
        usort($siblings, static fn (Page $left, Page $right): int => [
            $left->navigationPosition,
            $left->title,
            $left->id,
        ] <=> [
            $right->navigationPosition,
            $right->title,
            $right->id,
        ]);

        return $siblings;
    }

    /**
     * @param list<Page> $pages
     * @return list<Page>
     */
    private function positioned(array $pages, ?string $parentId, \DateTimeImmutable $updatedAt): array
    {
        return array_map(
            static fn (Page $candidate, int $position): Page => $candidate->relocate($parentId, $position, $updatedAt),
            $pages,
            array_keys($pages),
        );
    }

    /**
     * @param list<Page> $pages
     */
    private function findSavedPage(array $pages, string $id): Page
    {
        foreach ($pages as $page) {
            if ($page->id === $id) {
                return $page;
            }
        }

        throw new \LogicException('Die verschobene Seite wurde nicht gespeichert.');
    }
}
