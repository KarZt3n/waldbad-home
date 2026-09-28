<?php

namespace App\Logic\Membership\DirectDebit\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\Iban;

/**
 * Die Gläubigerdaten des Vereins für SEPA-Lastschriften (Singleton, analog
 * `ContributionRateSettings`). Alle Felder sind optional, solange noch nichts gepflegt ist — ein
 * Export ist erst mit vollständigen Daten möglich (`isComplete()`). Die BIC ist auch dann
 * optional: Innerhalb des SEPA-Raums reicht die IBAN.
 */
readonly class DirectDebitCreditor
{
    public ?string $creditorId;
    public ?string $iban;
    public ?string $bic;

    public function __construct(
        public ?string $name,
        ?string $creditorId,
        ?string $iban,
        ?string $bic,
    ) {
        $this->creditorId = self::normalizeOptional($creditorId);
        $this->iban = $iban === null || trim($iban) === '' ? null : Iban::normalize($iban);
        $this->bic = self::normalizeOptional($bic);

        if ($this->name !== null && (trim($this->name) === '' || mb_strlen($this->name) > 70)) {
            throw new BusinessRuleViolationException('Der Name des Zahlungsempfängers darf höchstens 70 Zeichen lang sein.');
        }
        if ($this->creditorId !== null && !self::isValidCreditorId($this->creditorId)) {
            throw new BusinessRuleViolationException('Die Gläubiger-Identifikationsnummer ist ungültig.');
        }
        if ($this->iban !== null && !Iban::isValid($this->iban)) {
            throw new BusinessRuleViolationException('Die IBAN des Vereinskontos ist ungültig.');
        }
        if ($this->bic !== null && preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $this->bic) !== 1) {
            throw new BusinessRuleViolationException('Die BIC des Vereinskontos ist ungültig.');
        }
    }

    public static function empty(): self
    {
        return new self(null, null, null, null);
    }

    public function isComplete(): bool
    {
        return $this->name !== null && $this->creditorId !== null && $this->iban !== null;
    }

    private static function normalizeOptional(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return strtoupper((string) preg_replace('/\s+/', '', $value));
    }

    /**
     * Aufbau: Länderkennzeichen, zwei Prüfziffern, drei Zeichen Geschäftsbereichskennung (fließt
     * nicht in die Prüfziffer ein), nationale Kennung. Die Prüfziffer wird wie bei der IBAN per
     * Modulo 97 über nationale Kennung + Länderkennzeichen + Prüfziffern gebildet.
     */
    private static function isValidCreditorId(string $creditorId): bool
    {
        if (preg_match('/^([A-Z]{2})(\d{2})[A-Z0-9]{3}([A-Z0-9]{1,28})$/', $creditorId, $parts) !== 1) {
            return false;
        }
        $numeric = '';
        foreach (str_split($parts[3].$parts[1].$parts[2]) as $character) {
            $numeric .= ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
        }
        $remainder = 0;
        foreach (str_split($numeric) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder === 1;
    }
}
