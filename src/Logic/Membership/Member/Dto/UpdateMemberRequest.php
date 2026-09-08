<?php

namespace App\Logic\Membership\Member\Dto;

use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\PaymentInterval;

readonly class UpdateMemberRequest
{
    public function __construct(
        public string $id,
        public int $version,
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
        /** Vorstandsmitglieder sind laut Satzung beitragsfrei (siehe Member::$contributionLiable). */
        public bool $contributionLiable = true,
        public ?\DateTimeImmutable $mandateValidFrom = null,
        public ?\DateTimeImmutable $mandateValidUntil = null,
    ) {
    }
}
