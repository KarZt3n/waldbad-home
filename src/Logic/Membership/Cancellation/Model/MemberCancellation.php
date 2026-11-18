<?php

namespace App\Logic\Membership\Cancellation\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Eine über „Meine Mitgliedschaft“ eingereichte Kündigung. Sie ändert das Mitglied noch nicht: Erst
 * mit der Bestätigung durch die Mitgliederverwaltung wird das Austrittsdatum gesetzt (siehe
 * `ConfirmMemberCancellationUseCase`). Name und Nummern sind zum Zeitpunkt der Kündigung
 * festgehalten, damit sie auch nach späteren Änderungen am Mitglied lesbar bleibt.
 *
 * Kündigt ein Zahler oder Hauptmitglied, hält die Kündigung fest, wer Zahler-Rolle bzw. Hauptnummer
 * (`$successorMemberId`) und wer die Kontaktdaten (`$contactRecipientMemberId`) übernehmen soll.
 */
readonly class MemberCancellation
{
    public function __construct(
        public string $id,
        public string $memberId,
        public string $memberNumber,
        public string $memberName,
        public string $primaryMemberNumber,
        public string $requestedByEmail,
        /** Wer gekündigt hat: das Mitglied mit der Zugangs-E-Mail-Adresse, null wenn keines sie führt. */
        public ?string $requestedByName,
        public ?string $requestedByMemberNumber,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $exitDate,
        public ?string $successorMemberId,
        public ?string $successorName,
        public ?string $contactRecipientMemberId,
        public ?string $contactRecipientName,
        public MemberCancellationStatus $status,
        public ?\DateTimeImmutable $decidedAt,
    ) {
        if ($this->exitDate->format('m-d') !== '12-31') {
            throw new BusinessRuleViolationException('Ein Austritt ist nur zum 31.12. möglich.');
        }
        if (($this->status === MemberCancellationStatus::Open) !== ($this->decidedAt === null)) {
            throw new BusinessRuleViolationException('Nur eine entschiedene Kündigung hat ein Entscheidungsdatum.');
        }
    }

    public function isOpen(): bool
    {
        return $this->status === MemberCancellationStatus::Open;
    }

    public function decide(MemberCancellationStatus $status, \DateTimeImmutable $decidedAt): self
    {
        if (!$this->isOpen()) {
            throw new BusinessRuleViolationException('Diese Kündigung wurde bereits bearbeitet.');
        }
        if ($status === MemberCancellationStatus::Open) {
            throw new BusinessRuleViolationException('Eine Kündigung kann nur bestätigt oder abgelehnt werden.');
        }

        return new self(
            id: $this->id,
            memberId: $this->memberId,
            memberNumber: $this->memberNumber,
            memberName: $this->memberName,
            primaryMemberNumber: $this->primaryMemberNumber,
            requestedByEmail: $this->requestedByEmail,
            requestedByName: $this->requestedByName,
            requestedByMemberNumber: $this->requestedByMemberNumber,
            submittedAt: $this->submittedAt,
            exitDate: $this->exitDate,
            successorMemberId: $this->successorMemberId,
            successorName: $this->successorName,
            contactRecipientMemberId: $this->contactRecipientMemberId,
            contactRecipientName: $this->contactRecipientName,
            status: $status,
            decidedAt: $decidedAt,
        );
    }
}
