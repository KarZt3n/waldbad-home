<?php

namespace App\Logic\Event\Template\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Event\Schedule\Model\EventScheduleKind;

/**
 * Wiederverwendbare Vorlage für eine Veranstaltung/einen Arbeitseinsatz (siehe
 * `App\Logic\Event\Schedule\Model\EventSchedule`, dessen Feld-Set diese Klasse bis auf `date`/
 * `time`/`visible` spiegelt — eine Vorlage hat bewusst keinen Termin und wird nie selbst im
 * Frontend angezeigt). Der Titel dient zugleich als Anzeigename der Vorlage und als Vorbelegung
 * für den Titel der daraus angelegten Veranstaltung (siehe `assets/admin/events.js`,
 * „Vorlage verwenden").
 */
readonly class EventTemplate
{
    /**
     * @param list<EventTemplateActivity> $activities
     * @param list<EventTemplateCallToAction> $callToActions
     */
    public function __construct(
        public string $id,
        public EventScheduleKind $kind,
        public string $title,
        public string $content,
        public ?string $mediaUrl,
        public ?string $mediaAlt,
        public ?string $mediaSource,
        public ?string $layout,
        public ?int $imageWidthPercent,
        public ?string $verticalAlignment,
        public ?string $textAlignment,
        public ?string $imageFit,
        public bool $helpEnabled,
        public ?string $helpButtonLabel,
        public array $activities,
        public array $callToActions,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
        if (trim($this->title) === '') {
            throw new BusinessRuleViolationException('Eine Vorlage benötigt eine Überschrift.');
        }
        if (mb_strlen(trim($this->title)) > 180) {
            throw new BusinessRuleViolationException('Die Überschrift darf höchstens 180 Zeichen lang sein.');
        }
        if ($this->helpButtonLabel !== null && mb_strlen(trim($this->helpButtonLabel)) > 80) {
            throw new BusinessRuleViolationException('Die Beschriftung der Helferanmeldung ist zu lang.');
        }
        if ($this->mediaSource !== null && mb_strlen(trim($this->mediaSource)) > 300) {
            throw new BusinessRuleViolationException('Die Bildquelle darf höchstens 300 Zeichen lang sein.');
        }

        $activityIds = array_map(static fn (EventTemplateActivity $activity): string => $activity->activityId, $this->activities);
        if (count($activityIds) !== count(array_unique($activityIds))) {
            throw new BusinessRuleViolationException('Eine Aktivität darf einer Vorlage nur einmal zugeordnet werden.');
        }
    }

    /**
     * @param list<EventTemplateActivity> $activities
     * @param list<EventTemplateCallToAction> $callToActions
     */
    public function revise(
        string $title,
        string $content,
        ?string $mediaUrl,
        ?string $mediaAlt,
        ?string $mediaSource,
        ?string $layout,
        ?int $imageWidthPercent,
        ?string $verticalAlignment,
        ?string $textAlignment,
        ?string $imageFit,
        bool $helpEnabled,
        ?string $helpButtonLabel,
        array $activities,
        array $callToActions,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $this->id,
            kind: $this->kind,
            title: $title,
            content: $content,
            mediaUrl: $mediaUrl,
            mediaAlt: $mediaAlt,
            mediaSource: $mediaSource,
            layout: $layout,
            imageWidthPercent: $imageWidthPercent,
            verticalAlignment: $verticalAlignment,
            textAlignment: $textAlignment,
            imageFit: $imageFit,
            helpEnabled: $helpEnabled,
            helpButtonLabel: $helpButtonLabel,
            activities: $activities,
            callToActions: $callToActions,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
        );
    }
}
