<?php

namespace App\Logic\Membership\Member\Dto;

final readonly class MandateBackfillRow
{
    /**
     * Null bei allen drei Feldern bedeutet: in der Quelle leer, bestehender Wert des Mitglieds
     * bleibt unangetastet (die Übernahme ergänzt fehlende Daten, überschreibt aber nichts mit
     * einem Leerwert).
     */
    public function __construct(
        public string $memberNumber,
        public ?string $mandateReference,
        public ?\DateTimeImmutable $mandateValidFrom,
        public ?\DateTimeImmutable $mandateValidUntil,
    ) {
    }
}
