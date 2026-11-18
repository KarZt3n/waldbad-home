<?php

namespace App\Logic\Membership\MemberAccess\Service;

use App\Logic\Membership\Member\Exception\MemberNotFoundException;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\PayerType;

/**
 * Wen „Meine Mitgliedschaft“ zeigt: den Zahler und alle Mitglieder, für die er zahlt. Ein
 * Selbstzahler im selben Haushalt (z. B. die Partnerin, die selbst zahlt) bildet eine eigene
 * Gruppe und ist für den anderen Zahler nicht sichtbar.
 */
readonly class MemberPayerGroupResolver
{
    public function __construct(private MemberManagerInterface $members)
    {
    }

    public function payerIdOf(Member $member): string
    {
        return $member->payerType === PayerType::OtherMember && $member->payerMemberId !== null
            ? $member->payerMemberId
            : $member->id;
    }

    /**
     * Folgt dabei dem heutigen Zahler: Ein Zugang speichert den Zahler beim Anfordern, wurde die
     * Zahler-Rolle seitdem übergeben (z. B. bei einer Kündigung), zahlt der gespeicherte selbst
     * über die Nachfolge und gehört weiter zu deren Strang.
     *
     * @return list<Member>
     */
    public function members(string $payerMemberId): array
    {
        try {
            $stored = $this->members->get($payerMemberId);
            $payerMemberId = $this->payerIdOf($stored);
            $payer = [$payerMemberId === $stored->id ? $stored : $this->members->get($payerMemberId)];
        } catch (MemberNotFoundException) {
            $payer = [];
        }
        $dependents = array_filter(
            $this->members->findByPayerMemberId($payerMemberId),
            static fn (Member $member): bool => $member->payerType === PayerType::OtherMember && $member->id !== $payerMemberId,
        );

        return [...$payer, ...array_values($dependents)];
    }
}
