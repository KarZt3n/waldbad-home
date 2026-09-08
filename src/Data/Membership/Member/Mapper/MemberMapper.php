<?php

namespace App\Data\Membership\Member\Mapper;

use App\Data\Membership\Member\Entity\MemberContributionChargeEntity;
use App\Data\Membership\Member\Entity\MemberEntity;
use App\Data\Membership\Member\Entity\MemberRemarkEntity;
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

readonly class MemberMapper
{
    public function toModel(MemberEntity $entity): Member
    {
        return new Member(
            id: $entity->getId(),
            memberNumber: $entity->getMemberNumber(),
            primaryMemberNumber: $entity->getPrimaryMemberNumber(),
            salutation: Salutation::from($entity->getSalutation()),
            lastName: $entity->getLastName(),
            firstName: $entity->getFirstName(),
            birthDate: $entity->getBirthDate(),
            street: $entity->getStreet(),
            postalCode: $entity->getPostalCode(),
            city: $entity->getCity(),
            email: $entity->getEmail(),
            phone: $entity->getPhone(),
            familyRole: FamilyRole::from($entity->getFamilyRole()),
            joinedAt: $entity->getJoinedAt(),
            leftAt: $entity->getLeftAt(),
            active: $entity->isActive(),
            function: MemberFunction::from($entity->getFunction()),
            contributionLiable: $entity->isContributionLiable(),
            accountHolder: $entity->getAccountHolder(),
            iban: $entity->getIban(),
            bankName: $entity->getBankName(),
            mandateReference: $entity->getMandateReference(),
            mandateValidFrom: $entity->getMandateValidFrom(),
            mandateValidUntil: $entity->getMandateValidUntil(),
            paymentMethod: PaymentMethod::from($entity->getPaymentMethod()),
            paymentInterval: PaymentInterval::from($entity->getPaymentInterval()),
            paymentDay: PaymentDay::from($entity->getPaymentDay()),
            payerType: PayerType::from($entity->getPayerType()),
            payerMemberId: $entity->getPayerMemberId(),
            nextBookingMonth: $entity->getNextBookingMonth(),
            nextBookingYear: $entity->getNextBookingYear(),
            contributionCategory: $entity->getContributionCategory() === null
                ? null
                : ContributionCategory::from($entity->getContributionCategory()),
            contributionAmountCents: $entity->getContributionAmountCents(),
            workAssignmentSurchargeCents: $entity->getWorkAssignmentSurchargeCents(),
            remarks: array_map(
                static fn (MemberRemarkEntity $remark): Remark => new Remark(
                    id: $remark->getId(),
                    text: $remark->getText(),
                    authorDisplayName: $remark->getAuthorDisplayName(),
                    createdAt: $remark->getCreatedAt(),
                ),
                $entity->getRemarks(),
            ),
            oneTimeCharges: array_map(
                static fn (MemberContributionChargeEntity $charge): ContributionCharge => new ContributionCharge(
                    id: $charge->getId(),
                    label: $charge->getLabel(),
                    amountCents: $charge->getAmountCents(),
                    chargedAt: $charge->getChargedAt(),
                ),
                $entity->getOneTimeCharges(),
            ),
            version: $entity->getVersion(),
        );
    }

    public function createEntity(Member $member, \DateTimeImmutable $at): MemberEntity
    {
        $entity = new MemberEntity(
            id: $member->id,
            memberNumber: $member->memberNumber,
            primaryMemberNumber: $member->primaryMemberNumber,
            salutation: $member->salutation->value,
            lastName: $member->lastName,
            firstName: $member->firstName,
            birthDate: $member->birthDate,
            street: $member->street,
            postalCode: $member->postalCode,
            city: $member->city,
            email: $member->email,
            phone: $member->phone,
            familyRole: $member->familyRole->value,
            joinedAt: $member->joinedAt,
            leftAt: $member->leftAt,
            active: $member->active,
            function: $member->function->value,
            contributionLiable: $member->contributionLiable,
            accountHolder: $member->accountHolder,
            iban: $member->iban,
            bankName: $member->bankName,
            mandateReference: $member->mandateReference,
            mandateValidFrom: $member->mandateValidFrom,
            mandateValidUntil: $member->mandateValidUntil,
            paymentMethod: $member->paymentMethod->value,
            paymentInterval: $member->paymentInterval->value,
            paymentDay: $member->paymentDay->value,
            payerType: $member->payerType->value,
            payerMemberId: $member->payerMemberId,
            nextBookingMonth: $member->nextBookingMonth,
            nextBookingYear: $member->nextBookingYear,
            contributionCategory: $member->contributionCategory?->value,
            contributionAmountCents: $member->contributionAmountCents,
            workAssignmentSurchargeCents: $member->workAssignmentSurchargeCents,
            createdAt: $at,
            updatedAt: $at,
        );
        foreach ($member->remarks as $remark) {
            $entity->addRemark(new MemberRemarkEntity(
                id: $remark->id,
                member: $entity,
                text: $remark->text,
                authorDisplayName: $remark->authorDisplayName,
                createdAt: $remark->createdAt,
            ));
        }
        foreach ($member->oneTimeCharges as $charge) {
            $entity->addOneTimeCharge(new MemberContributionChargeEntity(
                id: $charge->id,
                member: $entity,
                label: $charge->label,
                amountCents: $charge->amountCents,
                chargedAt: $charge->chargedAt,
            ));
        }

        return $entity;
    }

    public function updateEntity(Member $member, MemberEntity $entity, \DateTimeImmutable $updatedAt): void
    {
        $entity->update(
            memberNumber: $member->memberNumber,
            primaryMemberNumber: $member->primaryMemberNumber,
            salutation: $member->salutation->value,
            lastName: $member->lastName,
            firstName: $member->firstName,
            birthDate: $member->birthDate,
            street: $member->street,
            postalCode: $member->postalCode,
            city: $member->city,
            email: $member->email,
            phone: $member->phone,
            familyRole: $member->familyRole->value,
            joinedAt: $member->joinedAt,
            leftAt: $member->leftAt,
            active: $member->active,
            function: $member->function->value,
            contributionLiable: $member->contributionLiable,
            accountHolder: $member->accountHolder,
            iban: $member->iban,
            bankName: $member->bankName,
            mandateReference: $member->mandateReference,
            mandateValidFrom: $member->mandateValidFrom,
            mandateValidUntil: $member->mandateValidUntil,
            paymentMethod: $member->paymentMethod->value,
            paymentInterval: $member->paymentInterval->value,
            paymentDay: $member->paymentDay->value,
            payerType: $member->payerType->value,
            payerMemberId: $member->payerMemberId,
            nextBookingMonth: $member->nextBookingMonth,
            nextBookingYear: $member->nextBookingYear,
            contributionCategory: $member->contributionCategory?->value,
            contributionAmountCents: $member->contributionAmountCents,
            workAssignmentSurchargeCents: $member->workAssignmentSurchargeCents,
            updatedAt: $updatedAt,
        );

        $existingRemarkIds = array_map(static fn (MemberRemarkEntity $remark): string => $remark->getId(), $entity->getRemarks());
        foreach ($member->remarks as $remark) {
            if (!in_array($remark->id, $existingRemarkIds, true)) {
                $entity->addRemark(new MemberRemarkEntity(
                    id: $remark->id,
                    member: $entity,
                    text: $remark->text,
                    authorDisplayName: $remark->authorDisplayName,
                    createdAt: $remark->createdAt,
                ));
            }
        }

        $existingChargeIds = array_map(static fn (MemberContributionChargeEntity $charge): string => $charge->getId(), $entity->getOneTimeCharges());
        foreach ($member->oneTimeCharges as $charge) {
            if (!in_array($charge->id, $existingChargeIds, true)) {
                $entity->addOneTimeCharge(new MemberContributionChargeEntity(
                    id: $charge->id,
                    member: $entity,
                    label: $charge->label,
                    amountCents: $charge->amountCents,
                    chargedAt: $charge->chargedAt,
                ));
            }
        }
    }
}
