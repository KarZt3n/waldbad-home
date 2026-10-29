<?php

namespace App\Logic\Rental\Sauna\Booking\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Link „Anfrage annehmen“ aus der Benachrichtigung über eine neue Sauna-Anmeldung: Wer die Mail
 * erhalten hat, kann die darin genannten Termine ohne Anmeldung in der Redaktion annehmen. Einmalig
 * verwendbar (`$usedAt`) und zeitlich begrenzt. Gespeichert wird nur der SHA-256-Hash des Tokens
 * (Vorbild: `MemberEmailConsentToken`).
 */
readonly class SaunaBookingApprovalToken
{
    /**
     * @param non-empty-list<string> $bookingIds alle Termine der gemeldeten Anfrage
     */
    public function __construct(
        public string $id,
        public string $tokenHash,
        public array $bookingIds,
        public \DateTimeImmutable $expiresAt,
        public ?\DateTimeImmutable $usedAt = null,
    ) {
        if (trim($this->tokenHash) === '') {
            throw new BusinessRuleViolationException('Der Token-Hash ist erforderlich.');
        }
    }

    public function isUsable(\DateTimeImmutable $at): bool
    {
        return $this->usedAt === null && $at < $this->expiresAt;
    }

    public function markUsed(\DateTimeImmutable $at): self
    {
        return new self($this->id, $this->tokenHash, $this->bookingIds, $this->expiresAt, $at);
    }
}
