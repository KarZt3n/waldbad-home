<?php

namespace App\Logic\Membership\Member\Dto;

use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Member\Model\ContributionCharge;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Remark;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\PaymentInterval;

readonly class MemberResponse
{
    /**
     * @param list<Remark> $remarks
     * @param list<ContributionCharge> $oneTimeCharges
     */
    public function __construct(
        public string $id,
        public string $memberNumber,
        public string $primaryMemberNumber,
        public Salutation $salutation,
        public string $lastName,
        public string $firstName,
        public \DateTimeImmutable $birthDate,
        public string $street,
        public string $postalCode,
        public string $city,
        public ?string $email,
        public ?string $phone,
        public FamilyRole $familyRole,
        public \DateTimeImmutable $joinedAt,
        public ?\DateTimeImmutable $leftAt,
        public bool $active,
        public MemberFunction $function,
        public ?string $accountHolder,
        public ?string $iban,
        public ?string $bankName,
        public ?string $mandateReference,
        public PaymentMethod $paymentMethod,
        public PaymentInterval $paymentInterval,
        public PaymentDay $paymentDay,
        public PayerType $payerType,
        public ?string $payerMemberId,
        public int $nextBookingMonth,
        public int $nextBookingYear,
        public ?ContributionCategory $contributionCategory,
        public ?int $contributionAmountCents,
        public ?int $workAssignmentSurchargeCents,
        public array $remarks,
        public array $oneTimeCharges,
        public int $version,
        public bool $contributionLiable = true,
        public ?\DateTimeImmutable $mandateValidFrom = null,
        public ?\DateTimeImmutable $mandateValidUntil = null,
    ) {
    }

    public static function fromMember(Member $member): self
    {
        return new self(
            id: $member->id,
            memberNumber: $member->memberNumber,
            primaryMemberNumber: $member->primaryMemberNumber,
            salutation: $member->salutation,
            lastName: $member->lastName,
            firstName: $member->firstName,
            birthDate: $member->birthDate,
            street: $member->street,
            postalCode: $member->postalCode,
            city: $member->city,
            email: $member->email,
            phone: $member->phone,
            familyRole: $member->familyRole,
            joinedAt: $member->joinedAt,
            leftAt: $member->leftAt,
            active: $member->active,
            function: $member->function,
            accountHolder: $member->accountHolder,
            iban: $member->iban,
            bankName: $member->bankName,
            mandateReference: $member->mandateReference,
            paymentMethod: $member->paymentMethod,
            paymentInterval: $member->paymentInterval,
            paymentDay: $member->paymentDay,
            payerType: $member->payerType,
            payerMemberId: $member->payerMemberId,
            nextBookingMonth: $member->nextBookingMonth,
            nextBookingYear: $member->nextBookingYear,
            contributionCategory: $member->contributionCategory,
            contributionAmountCents: $member->contributionAmountCents,
            workAssignmentSurchargeCents: $member->workAssignmentSurchargeCents,
            remarks: $member->remarks,
            oneTimeCharges: $member->oneTimeCharges,
            version: $member->version,
            contributionLiable: $member->contributionLiable,
            mandateValidFrom: $member->mandateValidFrom,
            mandateValidUntil: $member->mandateValidUntil,
        );
    }
}
