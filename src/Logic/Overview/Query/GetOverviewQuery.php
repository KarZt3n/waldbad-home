<?php

namespace App\Logic\Overview\Query;

use App\Logic\Overview\Dto\GetOverviewRequest;
use App\Logic\Overview\Dto\OverviewTaskResponse;
use App\Logic\Overview\Model\OverviewTaskType;
use App\Logic\Overview\OverviewTaskSourceInterface;

/** Zählt die offenen Aufgaben aller Module, die die angemeldete Person sehen darf. */
readonly class GetOverviewQuery
{
    /**
     * @param iterable<OverviewTaskSourceInterface> $sources
     */
    public function __construct(private iterable $sources)
    {
    }

    /**
     * @return list<OverviewTaskResponse> in der Reihenfolge von {@see OverviewTaskType}
     */
    public function execute(GetOverviewRequest $request): array
    {
        $tasks = [];
        foreach ($this->sources as $source) {
            $type = $source->type();
            if (!in_array($type->module(), $request->modules, true)) {
                continue;
            }
            $dates = $source->openItemDates();
            $tasks[$type->value] = new OverviewTaskResponse(
                type: $type,
                count: count($dates),
                earliestAt: $dates === [] ? null : min($dates),
            );
        }

        return array_values(array_filter(array_map(
            static fn (OverviewTaskType $type): ?OverviewTaskResponse => $tasks[$type->value] ?? null,
            OverviewTaskType::cases(),
        )));
    }
}
