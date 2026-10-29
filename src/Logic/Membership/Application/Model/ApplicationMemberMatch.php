<?php

namespace App\Logic\Membership\Application\Model;

use App\Logic\Membership\Member\Model\Member;

/**
 * Ergebnis des Abgleichs eines Mitgliedsantrags mit den aktiven Mitgliedern (siehe
 * `ApplicationMemberMatcher`): welche Personen des Antrags schon Mitglied sind und in welche
 * bestehende Mitgliedschaft (Haushalt) der Antrag übernommen würde.
 */
readonly class ApplicationMemberMatch
{
    /**
     * @param array<int, Member> $matchedMembers je Personenposition im Antrag das gefundene Mitglied;
     *                                           Personen ohne Eintrag sind neu
     * @param list<string> $conflictingMemberNumbers Hauptnummern weiterer Haushalte, in denen Personen
     *                                               des Antrags gefunden wurden
     * @param list<Member> $missingMembers aktive Mitglieder des Haushalts, die im Antrag fehlen
     */
    public function __construct(
        public string $primaryMemberNumber,
        public array $matchedMembers,
        public ?Member $bankAccountMember,
        public array $conflictingMemberNumbers = [],
        public array $missingMembers = [],
    ) {
    }

    /**
     * Warum ein fehlendes Mitglied bei der Übernahme nicht ausgetragen werden kann, oder `null`.
     * Hauptmitglied und Zahler tragen den Haushalt; sie zuerst in der Mitgliederverwaltung umstellen.
     */
    public function removalBlocker(Member $member): ?string
    {
        if ($member->memberNumber === $this->primaryMemberNumber) {
            return 'Hauptmitglied des Haushalts';
        }
        if ($this->bankAccountMember?->id === $member->id) {
            return 'zahlendes Mitglied des Antrags';
        }
        foreach ([...$this->matchedMembers, ...$this->missingMembers] as $other) {
            if ($other->id !== $member->id && $other->payerMemberId === $member->id) {
                return 'zahlt für weitere Mitglieder';
            }
        }

        return null;
    }

    public function hasConflict(): bool
    {
        return $this->conflictingMemberNumbers !== [];
    }
}
