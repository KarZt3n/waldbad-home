<?php

namespace App\Data\Media\Library\Adapter;

use App\Logic\Media\Library\MediaUsageSourceInterface;

/** Bündelt alle Module, die Bibliotheksbilder referenzieren. */
readonly class ChainMediaUsageSource implements MediaUsageSourceInterface
{
    /** @var list<MediaUsageSourceInterface> */
    private array $sources;

    public function __construct(
        ContentPageMediaUsageSource $pages,
        EventScheduleMediaUsageSource $eventSchedules,
        EventTemplateMediaUsageSource $eventTemplates,
    ) {
        $this->sources = [$pages, $eventSchedules, $eventTemplates];
    }

    public function findUsages(): array
    {
        $usages = [];
        foreach ($this->sources as $source) {
            array_push($usages, ...$source->findUsages());
        }

        return $usages;
    }

    public function replaceReferences(string $url, ?string $replacementUrl): int
    {
        $changed = 0;
        foreach ($this->sources as $source) {
            $changed += $source->replaceReferences($url, $replacementUrl);
        }

        return $changed;
    }
}
