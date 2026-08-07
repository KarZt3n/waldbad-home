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
        public ApplicationStatus $status,
        public ?string $externalReference,
        public ?string $failureReason,
        public int $version,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $processingAt,
        public ?\DateTimeImmutable $completedAt,
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

    public function claim(\DateTimeImmutable $at): self
    {
        if ($this->status !== ApplicationStatus::Pending) {
            throw new BusinessRuleViolationException('Nur offene Anträge können zur Verarbeitung übernommen werden.');
        }

        return $this->withStatus(ApplicationStatus::Processing, $at, $at, null, null, null);
    }

    public function complete(string $externalReference, \DateTimeImmutable $at): self
    {
        if ($this->status === ApplicationStatus::Done && $this->externalReference === $externalReference) {
            return $this;
        }
        if ($this->status !== ApplicationStatus::Processing) {
            throw new BusinessRuleViolationException('Nur ein Antrag in Verarbeitung kann abgeschlossen werden.');
        }
        if (trim($externalReference) === '') {
            throw new BusinessRuleViolationException('Die Referenz des Fremdsystems ist erforderlich.');
        }

        return $this->withStatus(ApplicationStatus::Done, $at, $this->processingAt, $at, trim($externalReference), null);
    }

    public function fail(string $reason, \DateTimeImmutable $at): self
    {
        if ($this->status !== ApplicationStatus::Processing) {
            throw new BusinessRuleViolationException('Nur ein Antrag in Verarbeitung kann als fehlgeschlagen markiert werden.');
        }

        return $this->withStatus(ApplicationStatus::Failed, $at, $this->processingAt, null, null, trim($reason));
    }

    public function retry(\DateTimeImmutable $at): self
    {
        if ($this->status !== ApplicationStatus::Failed) {
            throw new BusinessRuleViolationException('Nur ein fehlgeschlagener Antrag kann erneut bereitgestellt werden.');
        }

        return $this->withStatus(ApplicationStatus::Pending, $at, null, null, null, null);
    }

    private function withStatus(
        ApplicationStatus $status,
        \DateTimeImmutable $updatedAt,
        ?\DateTimeImmutable $processingAt,
        ?\DateTimeImmutable $completedAt,
        ?string $externalReference,
        ?string $failureReason,
    ): self {
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
            status: $status,
            externalReference: $externalReference,
            failureReason: $failureReason,
            version: $this->version,
            submittedAt: $this->submittedAt,
            updatedAt: $updatedAt,
            processingAt: $processingAt,
            completedAt: $completedAt,
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
