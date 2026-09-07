<?php

namespace App\Logic\Membership\Member\Dto;

use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\PaymentInterval;

readonly class CreateMemberRequest
{
    public function __construct(
        /** Wird auf null gelassen, damit das nächste freie Mitgliedsnummern-Kürzel automatisch vergeben wird; nur beim Import bereits vorhandener Datensätze wird eine konkrete Nummer übergeben. */
        public ?string $memberNumber,
        /** Null bedeutet: eigene (neu vergebene) Mitgliedsnummer, d. h. kein Familienmitglied bzw. Hauptmitglied einer Familie. */
        public ?string $primaryMemberNumber,
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
        /** Nur für Selbstzahler erforderlich. */
        public ?string $accountHolder,
        /** Nur für Selbstzahler erforderlich. */
        public ?string $iban,
        public ?string $bankName,
        /** Null bedeutet: Mandatsreferenz = eigene Mitgliedsnummer (nur für Selbstzahler relevant). */
        public ?string $mandateReference,
        public PaymentMethod $paymentMethod,
        public PaymentInterval $paymentInterval,
        public PaymentDay $paymentDay,
        public PayerType $payerType,
        /** Interne ID des zahlenden Mitglieds, falls bereits bekannt (z. B. manuelle Anlage im Overlay). */
        public ?string $payerMemberId,
        /** Mitgliedsnummer des zahlenden Mitglieds; wird beim Anlegen zur internen ID aufgelöst — praktisch für Import/Freigabe, wo nur die Nummer bekannt ist. Hat Vorrang vor $payerMemberId, falls beide gesetzt sind. */
        public ?string $payerMemberNumber,
        /** Null bedeutet: März des Folgejahres nach Eintrittsdatum. */
        public ?int $nextBookingMonth,
        public ?int $nextBookingYear,
    ) {
    }
}
