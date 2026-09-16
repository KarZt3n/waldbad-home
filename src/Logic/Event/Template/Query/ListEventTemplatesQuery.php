<?php

namespace App\Logic\Event\Template\Query;

use App\Logic\Event\Template\Dto\EventTemplateResponse;
use App\Logic\Event\Template\Manager\EventTemplateManagerInterface;
use App\Logic\Event\Template\Model\EventTemplate;

readonly class ListEventTemplatesQuery
{
    public function __construct(private EventTemplateManagerInterface $manager)
    {
    }

    /** @return list<EventTemplateResponse> */
    public function execute(): array
    {
        $templates = $this->manager->all();
        usort($templates, static function (EventTemplate $left, EventTemplate $right): int {
            $kindComparison = $left->kind->value <=> $right->kind->value;

            return $kindComparison !== 0 ? $kindComparison : $left->title <=> $right->title;
        });

        return array_map(EventTemplateResponse::fromTemplate(...), $templates);
    }
}
