<?php

namespace App\UI\Membership\Member\Http;

use App\Logic\Membership\Member\Dto\MemberHouseholdResponse;
use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\Exception\MemberNotFoundException;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\ContributionCharge;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\Remark;

readonly class MemberResponseFactory
{
    public function __construct(private MemberManagerInterface $manager)
    {
    }

    /**
     * @param list<MemberResponse> $members
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function collection(array $members): array
    {
        return [
            'items' => array_map($this->member(...), $members),
            'total' => count($members),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function member(MemberResponse $member): array
    {
        $payerMember = $member->payerMemberId === null ? null : $this->findPayer($member->payerMemberId);

        return [
            'id' => $member->id,
            'memberNumber' => $member->memberNumber,
            'primaryMemberNumber' => $member->primaryMemberNumber,
            'salutation' => $member->salutation->value,
            'lastName' => $member->lastName,
            'firstName' => $member->firstName,
            'birthDate' => $member->birthDate->format('Y-m-d'),
            'street' => $member->street,
            'postalCode' => $member->postalCode,
            'city' => $member->city,
            'email' => $member->email,
            'phone' => $member->phone,
            'familyRole' => $member->familyRole->value,
            'joinedAt' => $member->joinedAt->format('Y-m-d'),
            'leftAt' => $member->leftAt?->format('Y-m-d'),
            'active' => $member->active,
            'function' => $member->function->value,
            'contributionLiable' => $member->contributionLiable,
            'accountHolder' => $member->accountHolder,
            'iban' => $member->iban,
            'bankName' => $member->bankName,
            'mandateReference' => $member->mandateReference,
            'mandateValidFrom' => $member->mandateValidFrom?->format('Y-m-d'),
            'mandateValidUntil' => $member->mandateValidUntil?->format('Y-m-d'),
            'paymentMethod' => $member->paymentMethod->value,
            'paymentInterval' => $member->paymentInterval->value,
            'paymentDay' => $member->paymentDay->value,
            'payerType' => $member->payerType->value,
            'payerMemberId' => $member->payerMemberId,
            'payerDisplayName' => $payerMember === null ? null : sprintf('%s %s (%s)', $payerMember->firstName, $payerMember->lastName, $payerMember->memberNumber),
            'nextBookingMonth' => $member->nextBookingMonth,
            'nextBookingYear' => $member->nextBookingYear,
            'contributionCategory' => $member->contributionCategory?->value,
            'contributionAmountCents' => $member->contributionAmountCents,
            'workAssignmentSurchargeCents' => $member->workAssignmentSurchargeCents,
            'remarks' => array_map($this->remark(...), $member->remarks),
            'oneTimeCharges' => array_map($this->oneTimeCharge(...), $member->oneTimeCharges),
            'version' => $member->version,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function household(MemberHouseholdResponse $household): array
    {
        return [
            'payer' => $this->summary($household->payer),
            'householdMembers' => array_map($this->summary(...), $household->householdMembers),
            'payerEntries' => array_map($this->summary(...), $household->payerEntries),
            'payerTotalAnnualCents' => $household->payerTotalAnnualCents,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(MemberResponse $member): array
    {
        return [
            'id' => $member->id,
            'memberNumber' => $member->memberNumber,
            'firstName' => $member->firstName,
            'lastName' => $member->lastName,
            'familyRole' => $member->familyRole->value,
            'function' => $member->function->value,
            'contributionCategory' => $member->contributionCategory?->value,
            'contributionAmountCents' => $member->contributionAmountCents,
            'workAssignmentSurchargeCents' => $member->workAssignmentSurchargeCents,
            'contributionLiable' => $member->contributionLiable,
            'leftAt' => $member->leftAt?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function remark(Remark $remark): array
    {
        return [
            'id' => $remark->id,
            'text' => $remark->text,
            'authorDisplayName' => $remark->authorDisplayName,
            'createdAt' => $remark->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function oneTimeCharge(ContributionCharge $charge): array
    {
        return [
            'id' => $charge->id,
            'label' => $charge->label,
            'amountCents' => $charge->amountCents,
            'chargedAt' => $charge->chargedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    private function findPayer(string $payerMemberId): ?Member
    {
        try {
            return $this->manager->get($payerMemberId);
        } catch (MemberNotFoundException) {
            return null;
        }
    }
}
