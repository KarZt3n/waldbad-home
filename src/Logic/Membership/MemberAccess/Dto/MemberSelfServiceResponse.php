<?php

namespace App\Logic\Membership\MemberAccess\Dto;

use App\Logic\Membership\Member\Model\Member;

/**
 * Die für „Meine Mitgliedschaft“ (nur lesend, siehe `ResolveMemberAccessSessionUseCase`)
 * freigegebene Teilmenge der Mitgliedsdaten — bewusst ohne Bankdaten (Kontoinhaber, IBAN,
 * Mandatsreferenz): anders als im Admin-Bereich reicht hier ein kurzlebiger, nur per E-Mail
 * bestätigter Zugang, die eigene Bankverbindung ist dafür zu sensibel.
 */
readonly class MemberSelfServiceResponse
{
    public function __construct(
        public string $id,
        public string $memberNumber,
        public string $primaryMemberNumber,
        public string $salutation,
        public string $firstName,
        public string $lastName,
        public string $birthDate,
        public string $familyRole,
        public string $street,
        public string $postalCode,
        public string $city,
        public ?string $email,
        public ?string $phone,
        public string $function,
        public bool $active,
        public string $joinedAt,
        public ?string $leftAt,
        public bool $contributionLiable,
        public ?string $contributionCategoryLabel,
        public ?int $contributionAmountCents,
        public ?int $workAssignmentSurchargeCents,
        public string $paymentMethod,
        public string $paymentInterval,
        public string $paymentDay,
    ) {
    }

    public static function fromMember(Member $member, ?string $contributionCategoryLabel): self
    {
        return new self(
            id: $member->id,
            memberNumber: $member->memberNumber,
            primaryMemberNumber: $member->primaryMemberNumber,
            salutation: $member->salutation->value,
            firstName: $member->firstName,
            lastName: $member->lastName,
            birthDate: $member->birthDate->format('Y-m-d'),
            familyRole: $member->familyRole->value,
            street: $member->street,
            postalCode: $member->postalCode,
            city: $member->city,
            email: $member->email,
            phone: $member->phone,
            function: $member->function->value,
            active: $member->active,
            joinedAt: $member->joinedAt->format('Y-m-d'),
            leftAt: $member->leftAt?->format('Y-m-d'),
            contributionLiable: $member->contributionLiable,
            contributionCategoryLabel: $contributionCategoryLabel,
            contributionAmountCents: $member->contributionAmountCents,
            workAssignmentSurchargeCents: $member->workAssignmentSurchargeCents,
            paymentMethod: $member->paymentMethod->value,
            paymentInterval: $member->paymentInterval->value,
            paymentDay: $member->paymentDay->value,
        );
    }
}
