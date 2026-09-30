<?php

namespace App\Logic\Rental\Sauna\Booking\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;

readonly class SaunaBooking
{
    /**
     * @param list<SaunaBookingParticipant> $participants Namen aller Personen der Gruppe (bei individuellen
     *                                                    Anfragen erfasst, sonst leer); wenn vorhanden,
     *                                                    genau `$personCount` Einträge
     */
    public function __construct(
        public string $id,
        public \DateTimeImmutable $date,
        public string $startTime,
        public string $endTime,
        public int $personCount,
        /** Preis der gesamten Buchung in Cent, zum Zeitpunkt der Anfrage aus den Konditionen berechnet. */
        public int $priceCents,
        public string $firstName,
        public string $lastName,
        public \DateTimeImmutable $birthDate,
        public ?string $email,
        public string $message,
        public SaunaBookingStatus $status,
        /** Verweis auf `Member::$id` aus dem automatischen Abgleich beim Absenden, ohne DB-Fremdschlüssel (analog zu `EventHelpRequest::$memberId`). */
        public ?string $memberId,
        /** Mitgliedsnummer zum Zeitpunkt des Abgleichs — Anzeige in der Verwaltung ohne Zugriff auf das Mitgliedermodul. */
        public ?string $memberNumber,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
        /** Freie Wunschzeit innerhalb der Saison, aber außerhalb ihres Zeitrasters („individuelle Anfrage“). */
        public bool $individual = false,
        /**
         * Gemeinsame Kennung aller Buchungen, die mit einer individuellen Anfrage zusammen angefragt
         * wurden (je Wunschtag eine Buchung) — sonst `null`.
         */
        public ?string $requestId = null,
        public array $participants = [],
    ) {
        if ($this->participants !== [] && count($this->participants) !== $this->personCount) {
            throw new BusinessRuleViolationException('Für jede Person der Gruppe sind Vorname und Nachname anzugeben.');
        }
        if (trim($this->firstName) === '' || trim($this->lastName) === '') {
            throw new BusinessRuleViolationException('Vorname und Nachname sind erforderlich.');
        }
        if (mb_strlen($this->firstName) > 120 || mb_strlen($this->lastName) > 120 || mb_strlen($this->message) > 2000) {
            throw new BusinessRuleViolationException('Die Sauna-Anmeldung überschreitet die erlaubte Länge.');
        }
        if ($this->email !== null && (mb_strlen($this->email) > 180 || filter_var($this->email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new BusinessRuleViolationException('Die E-Mail-Adresse ist ungültig.');
        }
        if ($this->birthDate->format('Y-m-d') > $this->submittedAt->format('Y-m-d')) {
            throw new BusinessRuleViolationException('Das Geburtsdatum darf nicht in der Zukunft liegen.');
        }
        if (SaunaOpeningHours::toMinutes($this->startTime) >= SaunaOpeningHours::toMinutes($this->endTime)) {
            throw new BusinessRuleViolationException('Das Ende der Sauna-Buchung muss nach deren Beginn liegen.');
        }
        if ($this->personCount < 1) {
            throw new BusinessRuleViolationException('Die Personenzahl der Sauna-Buchung ist ungültig.');
        }
        if ($this->priceCents < 0) {
            throw new BusinessRuleViolationException('Der Preis der Sauna-Buchung darf nicht negativ sein.');
        }
    }

    public function accept(\DateTimeImmutable $updatedAt): self
    {
        if ($this->status === SaunaBookingStatus::Accepted) {
            throw new BusinessRuleViolationException('Die Sauna-Anmeldung wurde bereits angenommen.');
        }

        return $this->withStatus(SaunaBookingStatus::Accepted, $updatedAt);
    }

    public function reject(\DateTimeImmutable $updatedAt): self
    {
        if ($this->status === SaunaBookingStatus::Rejected) {
            throw new BusinessRuleViolationException('Die Sauna-Anmeldung wurde bereits abgelehnt.');
        }

        return $this->withStatus(SaunaBookingStatus::Rejected, $updatedAt);
    }

    /** Storno eines angenommenen Termins, z. B. wenn die anfragende Person einen Tag absagt. */
    public function cancel(\DateTimeImmutable $updatedAt): self
    {
        if ($this->status !== SaunaBookingStatus::Accepted) {
            throw new BusinessRuleViolationException('Nur angenommene Sauna-Anmeldungen können storniert werden.');
        }

        return $this->withStatus(SaunaBookingStatus::Cancelled, $updatedAt);
    }

    /** Offene Anfragen reservieren den Zeitraum bereits, damit er nicht doppelt angefragt wird. */
    public function blocksTime(): bool
    {
        return $this->status !== SaunaBookingStatus::Rejected && $this->status !== SaunaBookingStatus::Cancelled;
    }

    /**
     * Kennung der anfragenden Person, unter der die Verwaltung alle ihre Anmeldungen zusammenfasst:
     * das zugeordnete Mitglied, sonst Vor-/Nachname und Geburtsdatum (unabhängig von
     * Groß-/Kleinschreibung) — die E-Mail-Adresse ist optional und daher kein verlässliches Merkmal.
     */
    public function requesterKey(): string
    {
        $identity = $this->memberId !== null
            ? 'member:'.$this->memberId
            : 'guest:'.mb_strtolower(trim($this->firstName)).'|'.mb_strtolower(trim($this->lastName)).'|'.$this->birthDate->format('Y-m-d');

        return hash('sha256', $identity);
    }

    public function overlaps(\DateTimeImmutable $date, string $startTime, string $endTime): bool
    {
        return $this->date->format('Y-m-d') === $date->format('Y-m-d')
            && SaunaOpeningHours::toMinutes($this->startTime) < SaunaOpeningHours::toMinutes($endTime)
            && SaunaOpeningHours::toMinutes($startTime) < SaunaOpeningHours::toMinutes($this->endTime);
    }

    private function withStatus(SaunaBookingStatus $status, \DateTimeImmutable $updatedAt): self
    {
        return new self(
            id: $this->id,
            date: $this->date,
            startTime: $this->startTime,
            endTime: $this->endTime,
            personCount: $this->personCount,
            priceCents: $this->priceCents,
            firstName: $this->firstName,
            lastName: $this->lastName,
            birthDate: $this->birthDate,
            email: $this->email,
            message: $this->message,
            status: $status,
            memberId: $this->memberId,
            memberNumber: $this->memberNumber,
            submittedAt: $this->submittedAt,
            updatedAt: $updatedAt,
            individual: $this->individual,
            requestId: $this->requestId,
            participants: $this->participants,
        );
    }
}
