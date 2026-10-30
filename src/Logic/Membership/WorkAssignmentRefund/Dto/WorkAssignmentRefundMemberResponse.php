<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Dto;

use App\Logic\Membership\WorkAssignment\Model\WorkAssignmentParticipation;

/** Ein Haushaltsmitglied in der Detailansicht der Gutschrift: Zuschlag und Einsätze im Beitragsjahr. */
readonly class WorkAssignmentRefundMemberResponse
{
    /**
     * @param list<WorkAssignmentParticipation> $participations neueste Veranstaltung zuerst
     */
    public function __construct(
        public string $memberId,
        public string $memberNumber,
        public string $name,
        public ?int $surchargeCents,
        public int $workedMinutes,
        public array $participations,
    ) {
    }
}
