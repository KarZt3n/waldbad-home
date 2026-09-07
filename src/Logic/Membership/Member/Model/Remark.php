<?php

namespace App\Logic\Membership\Member\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Eine mit Zeitstempel versehene Bemerkung zu einem Mitglied. Bemerkungen werden nur hinzugefügt,
 * nicht nachträglich verändert oder gelöscht (Historie).
 */
readonly class Remark
{
    public function __construct(
        public string $id,
        public string $text,
        public ?string $authorDisplayName,
        public \DateTimeImmutable $createdAt,
    ) {
        if (trim($this->text) === '') {
            throw new BusinessRuleViolationException('Eine Bemerkung darf nicht leer sein.');
        }
    }
}
