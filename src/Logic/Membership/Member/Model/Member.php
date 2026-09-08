<?php

namespace App\Logic\Membership\Member\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\PaymentInterval;

readonly class Member
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
        /**
         * Vorstandsmitglieder sind laut Satzung beitragsfrei (siehe MemberContributionCalculator).
         * Ist dieses Feld false, werden Konto-, Bank- und Zahlungsdaten in der Oberfläche gesperrt
         * und der Beitrag wird unabhängig von Kategorie/Alter mit 0 € berechnet.
         */
        public bool $contributionLiable = true,
        /** Beginn/Ende der Gültigkeit des SEPA-Mandats (aus dem Sage-GS-Bestand übernommen: MANDATABDATUM/MANDATBISDATUM). */
        public ?\DateTimeImmutable $mandateValidFrom = null,
        public ?\DateTimeImmutable $mandateValidUntil = null,
    ) {
        if (trim($this->memberNumber) === '') {
            throw new BusinessRuleViolationException('Die Mitgliedsnummer ist erforderlich.');
        }
        if (trim($this->primaryMemberNumber) === '') {
            throw new BusinessRuleViolationException('Die Hauptnummer ist erforderlich.');
        }
        if (trim($this->lastName) === '' || trim($this->firstName) === '') {
            throw new BusinessRuleViolationException('Vor- und Nachname sind erforderlich.');
        }
        if (trim($this->street) === '' || trim($this->city) === '') {
            throw new BusinessRuleViolationException('Straße und Ort sind erforderlich.');
        }
        if (preg_match('/^\d{5}$/', $this->postalCode) !== 1) {
            throw new BusinessRuleViolationException('Die Postleitzahl muss aus fünf Ziffern bestehen.');
        }
        if ($this->birthDate > new \DateTimeImmutable('today')) {
            throw new BusinessRuleViolationException('Das Geburtsdatum darf nicht in der Zukunft liegen.');
        }
        if ($this->email !== null && filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessRuleViolationException('Die E-Mail-Adresse ist ungültig.');
        }
        if ($this->leftAt !== null && $this->leftAt < $this->joinedAt) {
            throw new BusinessRuleViolationException('Das Austrittsdatum darf nicht vor dem Eintrittsdatum liegen.');
        }
        // Kontodaten (Kontoinhaber, IBAN, Mandatsreferenz) sind nur für Selbstzahler mit
        // SEPA-Lastschrift zwingend erforderlich — nur dafür existiert überhaupt ein
        // SEPA-Mandat. Bei Überweisung oder Barzahlung braucht auch ein Selbstzahler keine
        // eigene Bankverbindung. Ein Mitglied, für das ein anderes Mitglied zahlt, braucht
        // ohnehin keine eigene Bankverbindung. Ist trotzdem eine IBAN hinterlegt (z. B.
        // historisch), wird ihr Format dennoch geprüft.
        if ($this->contributionLiable && $this->payerType === PayerType::SelfPayer && $this->paymentMethod === PaymentMethod::SepaDirectDebit) {
            if ($this->accountHolder === null || trim($this->accountHolder) === '') {
                throw new BusinessRuleViolationException('Der Kontoinhaber ist für einen Selbstzahler mit SEPA-Lastschrift erforderlich.');
            }
            if ($this->iban === null || !$this->isValidIban($this->iban)) {
                throw new BusinessRuleViolationException('Die IBAN ist für einen Selbstzahler mit SEPA-Lastschrift erforderlich und muss gültig sein.');
            }
            if ($this->mandateReference === null || trim($this->mandateReference) === '') {
                throw new BusinessRuleViolationException('Die Mandatsreferenz ist für einen Selbstzahler mit SEPA-Lastschrift erforderlich.');
            }
        } elseif ($this->iban !== null && trim($this->iban) !== '' && !$this->isValidIban($this->iban)) {
            throw new BusinessRuleViolationException('Die IBAN ist ungültig.');
        }
        if ($this->payerType === PayerType::OtherMember && $this->payerMemberId === null) {
            throw new BusinessRuleViolationException('Bei einem abweichenden Zahler muss das zahlende Mitglied angegeben werden.');
        }
        if ($this->payerType === PayerType::SelfPayer && $this->payerMemberId !== null) {
            throw new BusinessRuleViolationException('Ein Selbstzahler kann kein abweichendes zahlendes Mitglied haben.');
        }
        if ($this->payerMemberId === $this->id) {
            throw new BusinessRuleViolationException('Ein Mitglied kann nicht sein eigener abweichender Zahler sein.');
        }
        if ($this->nextBookingMonth < 1 || $this->nextBookingMonth > 12) {
            throw new BusinessRuleViolationException('Der Monat der nächsten Buchung muss zwischen 1 und 12 liegen.');
        }
        if ($this->mandateValidFrom !== null && $this->mandateValidUntil !== null && $this->mandateValidUntil < $this->mandateValidFrom) {
            throw new BusinessRuleViolationException('Das Mandat kann nicht vor seinem Beginn enden.');
        }
    }

    public function age(\DateTimeImmutable $at): int
    {
        return (int) $this->birthDate->diff($at)->y;
    }

    /**
     * Ob das Austrittsdatum bereits erreicht ist (heute oder in der Vergangenheit liegt).
     */
    public function hasLeft(\DateTimeImmutable $at): bool
    {
        return $this->leftAt !== null && $this->leftAt <= $at;
    }

    public function withContribution(?ContributionCategory $category, ?int $amountCents, ?int $workAssignmentSurchargeCents): self
    {
        return new self(
            id: $this->id,
            memberNumber: $this->memberNumber,
            primaryMemberNumber: $this->primaryMemberNumber,
            salutation: $this->salutation,
            lastName: $this->lastName,
            firstName: $this->firstName,
            birthDate: $this->birthDate,
            street: $this->street,
            postalCode: $this->postalCode,
            city: $this->city,
            email: $this->email,
            phone: $this->phone,
            familyRole: $this->familyRole,
            joinedAt: $this->joinedAt,
            leftAt: $this->leftAt,
            active: $this->active,
            function: $this->function,
            accountHolder: $this->accountHolder,
            iban: $this->iban,
            bankName: $this->bankName,
            mandateReference: $this->mandateReference,
            paymentMethod: $this->paymentMethod,
            paymentInterval: $this->paymentInterval,
            paymentDay: $this->paymentDay,
            payerType: $this->payerType,
            payerMemberId: $this->payerMemberId,
            nextBookingMonth: $this->nextBookingMonth,
            nextBookingYear: $this->nextBookingYear,
            contributionCategory: $category,
            contributionAmountCents: $amountCents,
            workAssignmentSurchargeCents: $workAssignmentSurchargeCents,
            remarks: $this->remarks,
            oneTimeCharges: $this->oneTimeCharges,
            version: $this->version,
            contributionLiable: $this->contributionLiable,
            mandateValidFrom: $this->mandateValidFrom,
            mandateValidUntil: $this->mandateValidUntil,
        );
    }

    public function withOneTimeCharge(ContributionCharge $charge): self
    {
        return new self(
            id: $this->id,
            memberNumber: $this->memberNumber,
            primaryMemberNumber: $this->primaryMemberNumber,
            salutation: $this->salutation,
            lastName: $this->lastName,
            firstName: $this->firstName,
            birthDate: $this->birthDate,
            street: $this->street,
            postalCode: $this->postalCode,
            city: $this->city,
            email: $this->email,
            phone: $this->phone,
            familyRole: $this->familyRole,
            joinedAt: $this->joinedAt,
            leftAt: $this->leftAt,
            active: $this->active,
            function: $this->function,
            accountHolder: $this->accountHolder,
            iban: $this->iban,
            bankName: $this->bankName,
            mandateReference: $this->mandateReference,
            paymentMethod: $this->paymentMethod,
            paymentInterval: $this->paymentInterval,
            paymentDay: $this->paymentDay,
            payerType: $this->payerType,
            payerMemberId: $this->payerMemberId,
            nextBookingMonth: $this->nextBookingMonth,
            nextBookingYear: $this->nextBookingYear,
            contributionCategory: $this->contributionCategory,
            contributionAmountCents: $this->contributionAmountCents,
            workAssignmentSurchargeCents: $this->workAssignmentSurchargeCents,
            remarks: $this->remarks,
            oneTimeCharges: [...$this->oneTimeCharges, $charge],
            version: $this->version,
            contributionLiable: $this->contributionLiable,
            mandateValidFrom: $this->mandateValidFrom,
            mandateValidUntil: $this->mandateValidUntil,
        );
    }

    public function withRemark(Remark $remark): self
    {
        return new self(
            id: $this->id,
            memberNumber: $this->memberNumber,
            primaryMemberNumber: $this->primaryMemberNumber,
            salutation: $this->salutation,
            lastName: $this->lastName,
            firstName: $this->firstName,
            birthDate: $this->birthDate,
            street: $this->street,
            postalCode: $this->postalCode,
            city: $this->city,
            email: $this->email,
            phone: $this->phone,
            familyRole: $this->familyRole,
            joinedAt: $this->joinedAt,
            leftAt: $this->leftAt,
            active: $this->active,
            function: $this->function,
            accountHolder: $this->accountHolder,
            iban: $this->iban,
            bankName: $this->bankName,
            mandateReference: $this->mandateReference,
            paymentMethod: $this->paymentMethod,
            paymentInterval: $this->paymentInterval,
            paymentDay: $this->paymentDay,
            payerType: $this->payerType,
            payerMemberId: $this->payerMemberId,
            nextBookingMonth: $this->nextBookingMonth,
            nextBookingYear: $this->nextBookingYear,
            contributionCategory: $this->contributionCategory,
            contributionAmountCents: $this->contributionAmountCents,
            workAssignmentSurchargeCents: $this->workAssignmentSurchargeCents,
            remarks: [...$this->remarks, $remark],
            oneTimeCharges: $this->oneTimeCharges,
            version: $this->version,
            contributionLiable: $this->contributionLiable,
            mandateValidFrom: $this->mandateValidFrom,
            mandateValidUntil: $this->mandateValidUntil,
        );
    }

    /**
     * Für den nachträglichen Übernahme der Sage-GS-Mandatsdaten (Mandatsreferenz sowie
     * Gültigkeit von/bis) auf bereits importierte Mitglieder, ohne die übrigen Felder anzufassen.
     */
    public function withMandate(?string $mandateReference, ?\DateTimeImmutable $validFrom, ?\DateTimeImmutable $validUntil): self
    {
        return new self(
            id: $this->id,
            memberNumber: $this->memberNumber,
            primaryMemberNumber: $this->primaryMemberNumber,
            salutation: $this->salutation,
            lastName: $this->lastName,
            firstName: $this->firstName,
            birthDate: $this->birthDate,
            street: $this->street,
            postalCode: $this->postalCode,
            city: $this->city,
            email: $this->email,
            phone: $this->phone,
            familyRole: $this->familyRole,
            joinedAt: $this->joinedAt,
            leftAt: $this->leftAt,
            active: $this->active,
            function: $this->function,
            accountHolder: $this->accountHolder,
            iban: $this->iban,
            bankName: $this->bankName,
            mandateReference: $mandateReference,
            paymentMethod: $this->paymentMethod,
            paymentInterval: $this->paymentInterval,
            paymentDay: $this->paymentDay,
            payerType: $this->payerType,
            payerMemberId: $this->payerMemberId,
            nextBookingMonth: $this->nextBookingMonth,
            nextBookingYear: $this->nextBookingYear,
            contributionCategory: $this->contributionCategory,
            contributionAmountCents: $this->contributionAmountCents,
            workAssignmentSurchargeCents: $this->workAssignmentSurchargeCents,
            remarks: $this->remarks,
            oneTimeCharges: $this->oneTimeCharges,
            version: $this->version,
            contributionLiable: $this->contributionLiable,
            mandateValidFrom: $validFrom,
            mandateValidUntil: $validUntil,
        );
    }

    private function isValidIban(string $iban): bool
    {
        $normalized = strtoupper((string) preg_replace('/\s+/', '', $iban));
        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $normalized) !== 1) {
            return false;
        }

        $rearranged = substr($normalized, 4).substr($normalized, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $character) {
            $numeric .= ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
        }
        $remainder = 0;
        foreach (str_split($numeric) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder === 1;
    }
}
