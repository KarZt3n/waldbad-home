<?php

namespace App\Logic\Membership\WorkAssignment\Dto;

use App\Logic\Membership\MemberAccess\Dto\WorkAssignmentCreditResponse;
use App\Logic\Membership\WorkAssignment\Model\WorkAssignmentParticipation;

readonly class MemberWorkAssignmentYearResponse
{
    /**
     * @param list<WorkAssignmentParticipation> $participations
     * @param array<string, string> $memberNames Mitglieds-ID => Name, für die Zuordnung der Teilnahmen
     */
    public function __construct(
        public int $year,
        public WorkAssignmentCreditResponse $credit,
        public array $participations,
        public array $memberNames,
    ) {
    }
}
