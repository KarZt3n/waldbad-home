<?php

namespace App\Logic\Membership\MemberAccess\Dto;

readonly class MemberAccessSessionResponse
{
    /**
     * @param list<MemberSelfServiceResponse> $members
     */
    public function __construct(
        public string $email,
        public array $members,
        /**
         * Das jüngste `ContributionRate::$validFrom` unter den Beitragssätzen, die den angezeigten
         * Beträgen der Haushaltsmitglieder zugrunde liegen (siehe
         * `ResolveMemberAccessSessionUseCase`) — null, wenn dafür kein Beitragssatz ein Datum
         * hinterlegt hat. Für die Anzeige „Beitragsordnung / Beitragssätze – gültig ab …" in „Meine
         * Mitgliedschaft".
         */
        public ?string $contributionRatesValidFrom,
    ) {
    }
}
