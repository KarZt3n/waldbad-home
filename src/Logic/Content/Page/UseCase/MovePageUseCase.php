<?php

namespace App\Logic\Content\Page\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\Page;

readonly class MovePageUseCase
{
    public function __construct(
        private PageManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id, string $direction): PageResponse
    {
        $page = $this->manager->get($id);
        $siblings = array_values(array_filter(
            $this->manager->all(),
            static fn (Page $candidate): bool => $candidate->parentId === $page->parentId,
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

        $currentIndex = array_search($id, array_map(static fn (Page $sibling): string => $sibling->id, $siblings), true);
        $offset = match ($direction) {
            'up' => -1,
            'down' => 1,
            default => throw new BusinessRuleViolationException('Die Verschieberichtung ist ungültig.'),
        };
        $targetIndex = is_int($currentIndex) ? $currentIndex + $offset : -1;
        if (!is_int($currentIndex) || !isset($siblings[$targetIndex])) {
            throw new BusinessRuleViolationException('Die Seite kann in diese Richtung nicht weiter verschoben werden.');
        }

        [$siblings[$currentIndex], $siblings[$targetIndex]] = [$siblings[$targetIndex], $siblings[$currentIndex]];
        $now = $this->clock->now();
        $positioned = array_map(
            static fn (Page $sibling, int $position): Page => $sibling->reposition($position, $now),
            $siblings,
            array_keys($siblings),
        );
        $saved = $this->manager->saveAll($positioned);
        foreach ($saved as $savedPage) {
            if ($savedPage->id === $id) {
                return PageResponse::fromPage($savedPage);
            }
        }

        throw new \LogicException('Die verschobene Seite wurde nicht gespeichert.');
    }
}
