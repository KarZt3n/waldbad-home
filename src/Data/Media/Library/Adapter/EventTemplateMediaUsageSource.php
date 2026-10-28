<?php

namespace App\Data\Media\Library\Adapter;

use App\Logic\Event\Schedule\Model\EventScheduleKind;
use App\Logic\Event\Template\Query\ListEventTemplatesQuery;
use App\Logic\Event\Template\UseCase\ReplaceEventTemplateMediaReferenceUseCase;
use App\Logic\Media\Library\MediaUsageSourceInterface;
use App\Logic\Media\Library\Model\MediaUsage;
use App\Logic\Media\Library\Model\MediaUsageArea;

/** Adapter auf die Bilder der Veranstaltungsvorlagen. */
readonly class EventTemplateMediaUsageSource implements MediaUsageSourceInterface
{
    public function __construct(
        private ListEventTemplatesQuery $query,
        private ReplaceEventTemplateMediaReferenceUseCase $replaceUseCase,
    ) {
    }

    public function findUsages(): array
    {
        $usages = [];
        foreach ($this->query->execute() as $template) {
            if ($template->mediaUrl === null) {
                continue;
            }
            $usages[] = new MediaUsage(
                url: $template->mediaUrl,
                area: MediaUsageArea::EventTemplate,
                referenceId: $template->id,
                title: $template->title,
                detail: $template->kind === EventScheduleKind::WorkAssignment ? 'Vorlage für Arbeitseinsätze' : 'Vorlage für Veranstaltungen',
            );
        }

        return $usages;
    }

    public function replaceReferences(string $url, ?string $replacementUrl): int
    {
        return $this->replaceUseCase->execute($url, $replacementUrl);
    }
}
