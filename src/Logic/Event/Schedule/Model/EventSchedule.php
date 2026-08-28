<?php

namespace App\Logic\Event\Schedule\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class EventSchedule
{
    /**
     * @param list<EventScheduleActivity> $activities
     * @param list<EventScheduleCallToAction> $callToActions
     */
    public function __construct(
        public string $id,
        public EventScheduleKind $kind,
        public string $title,
        public string $date,
        public string $time,
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
        public bool $visible,
        public array $activities,
        public array $callToActions,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
        if (trim($this->title) === '') {
            throw new BusinessRuleViolationException('Eine Veranstaltung benötigt eine Überschrift.');
        }
        if (mb_strlen(trim($this->title)) > 180) {
            throw new BusinessRuleViolationException('Die Überschrift darf höchstens 180 Zeichen lang sein.');
        }
        if (!self::isValidDate($this->date)) {
            throw new BusinessRuleViolationException('Das Datum muss ein gültiges Datum im Format JJJJ-MM-TT sein.');
        }
        if (!self::isValidTime($this->time)) {
            throw new BusinessRuleViolationException('Die Uhrzeit muss im Format HH:MM angegeben werden.');
        }
        if ($this->helpButtonLabel !== null && mb_strlen(trim($this->helpButtonLabel)) > 80) {
            throw new BusinessRuleViolationException('Die Beschriftung der Helferanmeldung ist zu lang.');
        }
        if ($this->mediaSource !== null && mb_strlen(trim($this->mediaSource)) > 300) {
            throw new BusinessRuleViolationException('Die Bildquelle darf höchstens 300 Zeichen lang sein.');
        }

        $activityIds = array_map(static fn (EventScheduleActivity $activity): string => $activity->activityId, $this->activities);
        if (count($activityIds) !== count(array_unique($activityIds))) {
            throw new BusinessRuleViolationException('Eine Aktivität darf einer Veranstaltung nur einmal zugeordnet werden.');
        }
    }

    /**
     * @param list<EventScheduleActivity> $activities
     * @param list<EventScheduleCallToAction> $callToActions
     */
    public function revise(
        string $title,
        string $date,
        string $time,
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
        bool $visible,
        array $activities,
        array $callToActions,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $this->id,
            kind: $this->kind,
            title: $title,
            date: $date,
            time: $time,
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
            visible: $visible,
            activities: $activities,
            callToActions: $callToActions,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
        );
    }

    private static function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private static function isValidTime(string $time): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }
}
