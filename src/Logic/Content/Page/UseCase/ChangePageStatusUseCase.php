<?php

namespace App\Logic\Content\Page\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\Manager\PageManagerInterface;

readonly class ChangePageStatusUseCase
{
    public function __construct(
        private PageManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id, string $action): PageResponse
    {
        $page = $this->manager->get($id);
        $now = $this->clock->now();

        $page = match ($action) {
            'request-review' => $page->requestReview($now),
            'publish' => $page->publish($now),
            'unpublish' => $page->unpublish($now),
            'archive' => $page->archive($now),
            default => throw new BusinessRuleViolationException('Unbekannte Statusaktion.'),
        };

        return PageResponse::fromPage($this->manager->save($page));
    }
}
