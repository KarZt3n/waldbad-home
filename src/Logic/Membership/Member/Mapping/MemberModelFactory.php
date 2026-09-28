<?php

namespace App\Logic\Membership\Member\Mapping;

use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Dto\UpdateMemberRequest;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentMethod;

/**
 * Zahlt ein anderes Mitglied den Beitrag, hat das Mitglied weder eine eigene Zahlart noch eine
 * eigene Buchung oder Kontodaten: Die Zahlart wird „Keine Angabe“, nächste Buchung, Kontoinhaber,
 * IBAN, Bank, Mandatsreferenz und Mandatszeitraum werden beim Anlegen und Aktualisieren geleert —
 * unabhängig davon, was Formular oder Importdatei mitliefern. Selbstzahler ohne Angabe erhalten als nächste Buchung März des Folgejahres ihres
 * Eintritts.
 */
readonly class MemberModelFactory
{
    private const int DEFAULT_BOOKING_MONTH = 3;

    /**
     * Baut ein neues Mitglied. Mitgliedsnummer, Hauptnummer und Mandatsreferenz werden vom
     * `CreateMemberUseCase` aufgelöst (Default-Werte, Nummerngenerierung) und hier nur noch
     * übernommen.
     */
    public function createFromRequest(
        CreateMemberRequest $request,
        string $id,
        string $memberNumber,
        string $primaryMemberNumber,
        ?string $mandateReference,
        ?string $payerMemberId,
        ?int $nextBookingMonth,
        ?int $nextBookingYear,
    ): Member {
        $selfPayer = $request->payerType === PayerType::SelfPayer;

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
            function: $request->function,
            accountHolder: $selfPayer ? $request->accountHolder : null,
            iban: $selfPayer ? $request->iban : null,
            bankName: $selfPayer ? $request->bankName : null,
            emailConsent: $request->emailConsent,
            mandateReference: $selfPayer ? $mandateReference : null,
            paymentMethod: $selfPayer ? $request->paymentMethod : PaymentMethod::NotSpecified,
            paymentInterval: $request->paymentInterval,
            paymentDay: $request->paymentDay,
            payerType: $request->payerType,
            payerMemberId: $payerMemberId,
            nextBookingMonth: $selfPayer ? $nextBookingMonth ?? self::DEFAULT_BOOKING_MONTH : null,
            nextBookingYear: $selfPayer ? $nextBookingYear ?? $this->defaultBookingYear($request->joinedAt) : null,
            contributionCategory: null,
            contributionAmountCents: null,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 0,
            contributionLiable: $request->contributionLiable,
            mandateValidFrom: $selfPayer ? $request->mandateValidFrom : null,
            mandateValidUntil: $selfPayer ? $request->mandateValidUntil : null,
        );
    }

    /**
     * Vollständiger Rebuild eines bestehenden Mitglieds aus dem Bearbeitungsformular. Beitrag und
     * Bemerkungen werden nicht über dieses Formular geändert und daher vom aktuellen Stand
     * übernommen.
     */
    public function rebuildFromRequest(UpdateMemberRequest $request, Member $current): Member
    {
        $selfPayer = $request->payerType === PayerType::SelfPayer;

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
            function: $request->function,
            accountHolder: $selfPayer ? $request->accountHolder : null,
            iban: $selfPayer ? $request->iban : null,
            bankName: $selfPayer ? $request->bankName : null,
            emailConsent: $current->emailConsent,
            mandateReference: $selfPayer ? $request->mandateReference : null,
            paymentMethod: $selfPayer ? $request->paymentMethod : PaymentMethod::NotSpecified,
            paymentInterval: $request->paymentInterval,
            paymentDay: $request->paymentDay,
            payerType: $request->payerType,
            payerMemberId: $request->payerMemberId,
            nextBookingMonth: $selfPayer ? $request->nextBookingMonth ?? self::DEFAULT_BOOKING_MONTH : null,
            nextBookingYear: $selfPayer ? $request->nextBookingYear ?? $this->defaultBookingYear($request->joinedAt) : null,
            contributionCategory: $current->contributionCategory,
            contributionAmountCents: $current->contributionAmountCents,
            workAssignmentSurchargeCents: $current->workAssignmentSurchargeCents,
            remarks: $current->remarks,
            oneTimeCharges: $current->oneTimeCharges,
            version: $request->version,
            contributionLiable: $request->contributionLiable,
            mandateValidFrom: $selfPayer ? $request->mandateValidFrom : null,
            mandateValidUntil: $selfPayer ? $request->mandateValidUntil : null,
        );
    }

    private function defaultBookingYear(\DateTimeImmutable $joinedAt): int
    {
        return (int) $joinedAt->format('Y') + 1;
    }
}
