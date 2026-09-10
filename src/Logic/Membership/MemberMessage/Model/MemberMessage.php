<?php

namespace App\Logic\Membership\MemberMessage\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Eine über „Meine Mitgliedschaft" gesendete Nachricht (z. B. „meine Adresse hat sich geändert") —
 * fachlich eine eigene, an ein Mitglied gebundene Variante der öffentlichen Kontaktanfrage
 * (`ContactRequest`), bewusst als eigenes Modell statt diese wiederzuverwenden: der Absender ist
 * hier über einen `MemberAccessToken` bestätigt (nicht anonym) und an einen konkreten
 * Mitgliedsdatensatz gebunden. `$memberNumber`/`$memberName` sind zum Anzeigezeitpunkt dupliziert,
 * damit die Nachricht auch dann noch lesbar bleibt, wenn der Mitgliedsdatensatz sich seither
 * geändert hat oder gelöscht wurde.
 */
readonly class MemberMessage
{
    public function __construct(
        public string $id,
        public string $memberId,
        public string $memberNumber,
        public string $memberName,
        public string $message,
        public MemberMessageStatus $status,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
    ) {
        if (trim($this->message) === '') {
            throw new BusinessRuleViolationException('Die Nachricht darf nicht leer sein.');
        }
    }

    public function changeStatus(MemberMessageStatus $status, \DateTimeImmutable $updatedAt): self
    {
        return new self(
            id: $this->id,
            memberId: $this->memberId,
            memberNumber: $this->memberNumber,
            memberName: $this->memberName,
            message: $this->message,
            status: $status,
            submittedAt: $this->submittedAt,
            updatedAt: $updatedAt,
        );
    }
}
