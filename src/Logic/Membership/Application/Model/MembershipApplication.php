<?php

namespace App\Logic\Membership\Application\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class MembershipApplication
{
    /**
     * @param list<Applicant> $applicants
     */
    public function __construct(
        public string $id,
        public MembershipType $membershipType,
        public array $applicants,
        public string $accountHolder,
        public string $iban,
        public ?string $bankName,
        public string $signerName,
        public bool $emailConsent,
        public string $declarationVersion,
        public int $version,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $releasedAt = null,
        /** @var list<string>|null */
        public ?array $releasedMemberIds = null,
        public ?\DateTimeImmutable $rejectedAt = null,
        public ?string $rejectionReason = null,
    ) {
        $count = count($this->applicants);
        if ($count < 1 || $count > 8) {
            throw new BusinessRuleViolationException('Ein Antrag muss zwischen einer und acht Personen enthalten.');
        }
        if ($this->membershipType === MembershipType::Individual && $count !== 1) {
            throw new BusinessRuleViolationException('Eine Einzelmitgliedschaft darf nur eine Person enthalten.');
        }
        if ($this->membershipType === MembershipType::Family && $count < 2) {
            throw new BusinessRuleViolationException('Eine Familienmitgliedschaft benötigt mindestens zwei Personen.');
        }
        if ($this->applicants[0]->email === null) {
            throw new BusinessRuleViolationException('Für die erste Person ist eine E-Mail-Adresse erforderlich.');
        }
        if (trim($this->accountHolder) === '' || trim($this->signerName) === '') {
            throw new BusinessRuleViolationException('Kontoinhaber und bestätigende Person sind erforderlich.');
        }
        if (!$this->isValidIban($this->iban)) {
            throw new BusinessRuleViolationException('Die IBAN ist ungültig.');
        }
        if (trim($this->declarationVersion) === '') {
            throw new BusinessRuleViolationException('Die Version der Einwilligungserklärung fehlt.');
        }
    }

    /**
     * Markiert den Antrag als in Mitglieder überführt.
     *
     * @param list<string> $memberIds
     */
    public function release(array $memberIds, \DateTimeImmutable $at): self
    {
        if ($this->releasedAt !== null) {
            throw new BusinessRuleViolationException('Der Mitgliedsantrag wurde bereits als Mitglied angelegt.');
        }
        if ($this->rejectedAt !== null) {
            throw new BusinessRuleViolationException('Ein abgelehnter Mitgliedsantrag kann nicht mehr als Mitglied angelegt werden.');
        }
        if ($memberIds === []) {
            throw new BusinessRuleViolationException('Bei der Freigabe muss mindestens ein Mitglied angelegt werden.');
        }

        return new self(
            id: $this->id,
            membershipType: $this->membershipType,
            applicants: $this->applicants,
            accountHolder: $this->accountHolder,
            iban: $this->iban,
            bankName: $this->bankName,
            signerName: $this->signerName,
            emailConsent: $this->emailConsent,
            declarationVersion: $this->declarationVersion,
            version: $this->version,
            submittedAt: $this->submittedAt,
            updatedAt: $at,
            releasedAt: $at,
            releasedMemberIds: $memberIds,
            rejectedAt: $this->rejectedAt,
            rejectionReason: $this->rejectionReason,
        );
    }

    /**
     * Lehnt den Antrag ab: die Person(en) werden nicht als Mitglied angelegt.
     */
    public function reject(?string $reason, \DateTimeImmutable $at): self
    {
        if ($this->releasedAt !== null) {
            throw new BusinessRuleViolationException('Ein bereits als Mitglied angelegter Antrag kann nicht mehr abgelehnt werden.');
        }
        if ($this->rejectedAt !== null) {
            throw new BusinessRuleViolationException('Der Mitgliedsantrag wurde bereits abgelehnt.');
        }

        return new self(
            id: $this->id,
            membershipType: $this->membershipType,
            applicants: $this->applicants,
            accountHolder: $this->accountHolder,
            iban: $this->iban,
            bankName: $this->bankName,
            signerName: $this->signerName,
            emailConsent: $this->emailConsent,
            declarationVersion: $this->declarationVersion,
            version: $this->version,
            submittedAt: $this->submittedAt,
            updatedAt: $at,
            releasedAt: $this->releasedAt,
            releasedMemberIds: $this->releasedMemberIds,
            rejectedAt: $at,
            rejectionReason: $reason !== null && trim($reason) !== '' ? trim($reason) : null,
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
