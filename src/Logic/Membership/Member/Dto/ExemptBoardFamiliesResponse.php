<?php

namespace App\Logic\Membership\Member\Dto;

readonly class ExemptBoardFamiliesResponse
{
    /**
     * @param list<string> $updatedMemberNumbers Mitgliedsnummern, deren „Beitragspflichtig“ auf false gesetzt wurde (bzw. im Prüflauf würde).
     */
    public function __construct(
        public int $householdsAffected,
        public array $updatedMemberNumbers,
    ) {
    }
}
