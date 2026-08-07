<?php

namespace App\Logic\Membership\Application\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class Applicant
{
    public function __construct(
        public string $id,
        public int $position,
        public string $firstName,
        public string $lastName,
        public \DateTimeImmutable $birthDate,
        public string $street,
        public string $houseNumber,
        public string $postalCode,
        public string $city,
        public ?string $phone,
        public ?string $email,
    ) {
        if ($this->position < 0) {
            throw new BusinessRuleViolationException('Die Personenposition darf nicht negativ sein.');
        }
        if (trim($this->firstName) === '' || trim($this->lastName) === '') {
            throw new BusinessRuleViolationException('Vorname und Nachname sind für jede Person erforderlich.');
        }
        if (trim($this->street) === '' || trim($this->houseNumber) === '' || trim($this->city) === '') {
            throw new BusinessRuleViolationException('Straße, Hausnummer und Ort sind für jede Person erforderlich.');
        }
        if (preg_match('/^\d{5}$/', $this->postalCode) !== 1) {
            throw new BusinessRuleViolationException('Die Postleitzahl muss aus fünf Ziffern bestehen.');
        }
        if ($this->birthDate > new \DateTimeImmutable('today')) {
            throw new BusinessRuleViolationException('Das Geburtsdatum darf nicht in der Zukunft liegen.');
        }
        if ($this->email !== null && filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessRuleViolationException('Eine angegebene E-Mail-Adresse ist ungültig.');
        }
    }
}
