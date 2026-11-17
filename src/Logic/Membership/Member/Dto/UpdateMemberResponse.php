<?php

namespace App\Logic\Membership\Member\Dto;

/**
 * Ergebnis von „Änderungen speichern“: der gespeicherte Datensatz und — falls die anschließende
 * Neuberechnung der Familienbeiträge scheiterte — der Grund. Gespeichert ist dann trotzdem.
 */
final readonly class UpdateMemberResponse
{
    public function __construct(
        public MemberResponse $member,
        public ?string $contributionWarning = null,
    ) {
    }
}
