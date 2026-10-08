<?php

namespace App\Logic\Event\Template\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Identische Form und Validierung wie `App\Logic\Event\Schedule\Model\EventScheduleActivity` — eine
 * Vorlagen-Zuordnung wird beim Einlösen (`Vorlage verwenden`, siehe `assets/admin/events.js`)
 * unverändert in die neue Veranstaltung übernommen.
 */
readonly class EventTemplateActivity
{
    public function __construct(
        public string $id,
        public int $position,
        public string $activityId,
        public ?int $requiredHelpers,
        public ?string $time = null,
        public ?string $meetTime = null,
        public ?string $meetPlace = null,
        public ?string $remark = null,
        public bool $selectable = false,
    ) {
        if (trim($this->activityId) === '') {
            throw new BusinessRuleViolationException('Eine Aktivität benötigt eine Kennung.');
        }
        if ($this->selectable && $this->requiredHelpers === null) {
            throw new BusinessRuleViolationException('Für eine auswählbare Aktivität muss die benötigte Helferzahl angegeben werden.');
        }
        if (!$this->selectable && $this->requiredHelpers !== null) {
            throw new BusinessRuleViolationException('Eine nicht auswählbare Aktivität hat keine benötigte Helferzahl.');
        }
        if ($this->requiredHelpers !== null && ($this->requiredHelpers < 1 || $this->requiredHelpers > 999)) {
            throw new BusinessRuleViolationException('Die benötigte Helferzahl muss zwischen 1 und 999 liegen.');
        }
        if ($this->time !== null && !self::isValidTime($this->time)) {
            throw new BusinessRuleViolationException('Die Uhrzeit einer Aktivität muss im Format HH:MM angegeben werden.');
        }
        if ($this->meetTime !== null && !self::isValidTime($this->meetTime)) {
            throw new BusinessRuleViolationException('Die Treffzeit einer Aktivität muss im Format HH:MM angegeben werden.');
        }
        if ($this->meetPlace !== null && mb_strlen(trim($this->meetPlace)) > 160) {
            throw new BusinessRuleViolationException('Der Treffort einer Aktivität darf höchstens 160 Zeichen lang sein.');
        }
        if ($this->remark !== null && mb_strlen(trim($this->remark)) > 500) {
            throw new BusinessRuleViolationException('Die Bemerkung einer Aktivität darf höchstens 500 Zeichen lang sein.');
        }
    }

    private static function isValidTime(string $time): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }
}
