<?php

namespace App\Logic\Content\Page\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class EventActivityAssignment
{
    public function __construct(
        public string $activityId,
        public int $requiredHelpers,
    ) {
        if (trim($this->activityId) === '') {
            throw new BusinessRuleViolationException('Eine Aktivität benötigt eine Kennung.');
        }
        if ($this->requiredHelpers < 1 || $this->requiredHelpers > 999) {
            throw new BusinessRuleViolationException('Die benötigte Helferzahl muss zwischen 1 und 999 liegen.');
        }
    }
}
