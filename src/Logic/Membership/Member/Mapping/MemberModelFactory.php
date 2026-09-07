<?php

namespace App\Logic\Membership\Member\Mapping;

use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Dto\UpdateMemberRequest;
use App\Logic\Membership\Member\Model\Member;

readonly class MemberModelFactory
{
    /**
     * Baut ein neues Mitglied. Mitgliedsnummer, Hauptnummer, Mandatsreferenz und die nächste
     * Buchung werden vom `CreateMemberUseCase` aufgelöst (Default-Werte, Nummerngenerierung) und
     * hier nur noch übernommen.
     */
    public function createFromRequest(
        CreateMemberRequest $request,
        string $id,
        string $memberNumber,
        string $primaryMemberNumber,
        ?string $mandateReference,
        ?string $payerMemberId,
        int $nextBookingMonth,
        int $nextBookingYear,
    ): Member {
        return new Member(
            id: $id,
            memberNumber: $memberNumber,
            primaryMemberNumber: $primaryMemberNumber,
            salutation: $request->salutation,
            lastName: $request->lastName,
            firstName: $request->firstName,
            birthDate: $request->birthDate,
            street: $request->street,
            postalCode: $request->postalCode,
            city: $request->city,
            email: $request->email,
            phone: $request->phone,
            familyRole: $request->familyRole,
            joinedAt: $request->joinedAt,
            leftAt: $request->leftAt,
            active: $request->active,
            function: $request->function,
            accountHolder: $request->accountHolder,
            iban: $request->iban,
            bankName: $request->bankName,
            mandateReference: $mandateReference,
            paymentMethod: $request->paymentMethod,
            paymentInterval: $request->paymentInterval,
            paymentDay: $request->paymentDay,
            payerType: $request->payerType,
            payerMemberId: $payerMemberId,
            nextBookingMonth: $nextBookingMonth,
            nextBookingYear: $nextBookingYear,
            contributionCategory: null,
            contributionAmountCents: null,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 0,
        );
    }

    /**
     * Vollständiger Rebuild eines bestehenden Mitglieds aus dem Bearbeitungsformular. Beitrag und
     * Bemerkungen werden nicht über dieses Formular geändert und daher vom aktuellen Stand
     * übernommen.
     */
    public function rebuildFromRequest(UpdateMemberRequest $request, Member $current): Member
    {
        return new Member(
            id: $request->id,
            memberNumber: $request->memberNumber,
            primaryMemberNumber: $request->primaryMemberNumber,
            salutation: $request->salutation,
            lastName: $request->lastName,
            firstName: $request->firstName,
            birthDate: $request->birthDate,
            street: $request->street,
            postalCode: $request->postalCode,
            city: $request->city,
            email: $request->email,
            phone: $request->phone,
            familyRole: $request->familyRole,
            joinedAt: $request->joinedAt,
            leftAt: $request->leftAt,
            active: $request->active,
            function: $request->function,
            accountHolder: $request->accountHolder,
            iban: $request->iban,
            bankName: $request->bankName,
            mandateReference: $request->mandateReference,
            paymentMethod: $request->paymentMethod,
            paymentInterval: $request->paymentInterval,
            paymentDay: $request->paymentDay,
            payerType: $request->payerType,
            payerMemberId: $request->payerMemberId,
            nextBookingMonth: $request->nextBookingMonth,
            nextBookingYear: $request->nextBookingYear,
            contributionCategory: $current->contributionCategory,
            contributionAmountCents: $current->contributionAmountCents,
            workAssignmentSurchargeCents: $current->workAssignmentSurchargeCents,
            remarks: $current->remarks,
            oneTimeCharges: $current->oneTimeCharges,
            version: $request->version,
        );
    }
}
