<?php

namespace App\Logic\Membership\Cancellation\Service;

use App\Logic\Membership\Cancellation\Model\MemberCancellation;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\PayerType;

/**
 * Was beim Kündigen über „Meine Mitgliedschaft“ zu übergeben ist — dieselben Regeln wie beim Löschen
 * im Backend (siehe `DeleteMemberUseCase`), beschränkt auf die dort sichtbaren Mitglieder. Gekündigt
 * werden können mehrere Personen auf einmal; maßgeblich ist, wer danach übrig bleibt:
 *
 * - Zahlt eine gekündigte Person für Verbleibende oder ist sie deren Hauptmitglied, muss jemand
 *   Zahler-Rolle bzw. Hauptnummer übernehmen.
 * - Hat danach niemand mehr E-Mail oder Telefon, gehen die Kontaktdaten an eine verbleibende Person.
 *
 * Verbleibend (und damit infrage kommend) sind Mitglieder, die weder gekündigt werden noch austreten
 * noch bereits gekündigt haben. JavaScript-Gegenstück: `cancellationHandover` in
 * `assets/public/member-self-service.js`.
 */
final class CancellationHandover
{
    /**
     * @param list<Member>                      $cancelled
     * @param list<Member>                      $group
     * @param array<string, MemberCancellation> $openByMemberId
     *
     * @return list<Member>
     */
    public static function candidates(array $cancelled, array $group, array $openByMemberId): array
    {
        $cancelledIds = array_map(static fn (Member $member): string => $member->id, $cancelled);

        return array_values(array_filter(
            $group,
            static fn (Member $other): bool => !in_array($other->id, $cancelledIds, true) && $other->leftAt === null && !isset($openByMemberId[$other->id]),
        ));
    }

    /**
     * @param list<Member> $candidates
     */
    public static function requiresSuccessor(Member $member, array $candidates): bool
    {
        $paysForOthers = $member->payerType === PayerType::SelfPayer && array_any(
            $candidates,
            static fn (Member $other): bool => $other->payerType === PayerType::OtherMember && $other->payerMemberId === $member->id,
        );
        $headOfOthers = $member->memberNumber === $member->primaryMemberNumber && array_any(
            $candidates,
            static fn (Member $other): bool => $other->primaryMemberNumber === $member->memberNumber,
        );

        return $paysForOthers || $headOfOthers;
    }

    /**
     * @param list<Member> $cancelled
     * @param list<Member> $candidates
     */
    public static function requiresContactRecipient(array $cancelled, array $candidates): bool
    {
        return array_any($cancelled, static fn (Member $member): bool => $member->hasContactInfo())
            && $candidates !== []
            && !array_any($candidates, static fn (Member $other): bool => $other->hasContactInfo());
    }
}
