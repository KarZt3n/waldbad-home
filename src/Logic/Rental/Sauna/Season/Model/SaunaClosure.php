<?php

namespace App\Logic\Rental\Sauna\Season\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Schließzeit innerhalb einer Saison (z. B. Revision, Feiertage): an diesen Tagen ist die Sauna
 * trotz Wochenplan geschlossen — im Kalender als „Geschlossen“ angezeigt und weder aus dem
 * Kalender noch individuell anfragbar. Beide Tage zählen mit; ein einzelner Tag hat Beginn = Ende.
 */
readonly class SaunaClosure
{
    public const int MAX_REASON_LENGTH = 120;

    public function __construct(
        public \DateTimeImmutable $startsOn,
        public \DateTimeImmutable $endsOn,
        public string $reason = '',
    ) {
        if ($this->endsOn->format('Y-m-d') < $this->startsOn->format('Y-m-d')) {
            throw new BusinessRuleViolationException('Das Ende einer Schließzeit darf nicht vor ihrem Beginn liegen.');
        }
        if (mb_strlen($this->reason) > self::MAX_REASON_LENGTH) {
            throw new BusinessRuleViolationException(sprintf('Der Grund einer Schließzeit darf höchstens %d Zeichen lang sein.', self::MAX_REASON_LENGTH));
        }
    }

    public function covers(\DateTimeImmutable $date): bool
    {
        $day = $date->format('Y-m-d');

        return $day >= $this->startsOn->format('Y-m-d') && $day <= $this->endsOn->format('Y-m-d');
    }

    public function overlaps(self $other): bool
    {
        return $this->startsOn->format('Y-m-d') <= $other->endsOn->format('Y-m-d')
            && $other->startsOn->format('Y-m-d') <= $this->endsOn->format('Y-m-d');
    }

    /** Meldung für Anfragen an einem geschlossenen Tag, z. B. „Die Sauna ist am 15.10.2026 geschlossen (Revision).“ */
    public function messageFor(\DateTimeImmutable $date): string
    {
        return sprintf(
            'Die Sauna ist am %s geschlossen%s.',
            $date->format('d.m.Y'),
            trim($this->reason) === '' ? '' : ' ('.trim($this->reason).')',
        );
    }
}
