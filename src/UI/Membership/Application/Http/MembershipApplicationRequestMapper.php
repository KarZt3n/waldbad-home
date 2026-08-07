<?php

namespace App\UI\Membership\Application\Http;

use App\Logic\Membership\Application\Dto\ApplicantInput;
use App\Logic\Membership\Application\Dto\SubmitMembershipApplicationRequest;
use App\Logic\Membership\Application\Model\MembershipType;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class MembershipApplicationRequestMapper
{
    private const string DECLARATION_VERSION = '2026-01';

    public function submit(Request $request): SubmitMembershipApplicationRequest
    {
        $data = $request->getPayload();
        if (!$data->getBoolean('privacyAccepted') || !$data->getBoolean('termsAccepted') || !$data->getBoolean('sepaAccepted')) {
            throw new BadRequestHttpException('Datenschutz, Satzung/Beitragsordnung und SEPA-Ermächtigung müssen bestätigt werden.');
        }
        $typeValue = $this->requiredString($data, 'membershipType', 20);
        try {
            $membershipType = MembershipType::from($typeValue);
        } catch (\ValueError) {
            throw new BadRequestHttpException('Die Mitgliedschaftsart ist ungültig.');
        }

        $rawApplicants = $data->all('applicants');
        if (!array_is_list($rawApplicants) || $rawApplicants === [] || count($rawApplicants) > 8) {
            throw new BadRequestHttpException('Der Antrag muss zwischen einer und acht Personen enthalten.');
        }
        $applicants = [];
        foreach ($rawApplicants as $rawApplicant) {
            if (!is_array($rawApplicant)) {
                throw new BadRequestHttpException('Jede Person muss als Objekt übermittelt werden.');
            }
            $birthDate = $this->date($rawApplicant['birthDate'] ?? null);
            $applicants[] = new ApplicantInput(
                firstName: $this->arrayString($rawApplicant, 'firstName', 120),
                lastName: $this->arrayString($rawApplicant, 'lastName', 120),
                birthDate: $birthDate,
                street: $this->arrayString($rawApplicant, 'street', 180),
                houseNumber: $this->arrayString($rawApplicant, 'houseNumber', 20),
                postalCode: $this->arrayString($rawApplicant, 'postalCode', 5),
                city: $this->arrayString($rawApplicant, 'city', 180),
                phone: $this->nullableArrayString($rawApplicant, 'phone', 60),
                email: $this->nullableArrayString($rawApplicant, 'email', 180),
            );
        }

        return new SubmitMembershipApplicationRequest(
            membershipType: $membershipType,
            applicants: $applicants,
            accountHolder: $this->requiredString($data, 'accountHolder', 180),
            iban: $this->requiredString($data, 'iban', 40),
            bankName: $this->nullableString($data, 'bankName', 180),
            signerName: $this->requiredString($data, 'signerName', 180),
            emailConsent: $data->getBoolean('emailConsent'),
            declarationVersion: self::DECLARATION_VERSION,
        );
    }

    private function date(mixed $value): \DateTimeImmutable
    {
        if (!is_string($value)) {
            throw new BadRequestHttpException('Das Geburtsdatum ist erforderlich.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        if ($date === false || $date->format('Y-m-d') !== trim($value)) {
            throw new BadRequestHttpException('Ein Geburtsdatum ist ungültig.');
        }

        return $date;
    }

    /**
     * @param array<mixed> $data
     */
    private function arrayString(array $data, string $key, int $maxLength): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new BadRequestHttpException(sprintf('Das Personenfeld "%s" ist erforderlich.', $key));
        }
        $value = trim($value);
        if (mb_strlen($value) > $maxLength) {
            throw new BadRequestHttpException(sprintf('Das Personenfeld "%s" ist zu lang.', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function nullableArrayString(array $data, string $key, int $maxLength): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || mb_strlen(trim($value)) > $maxLength) {
            throw new BadRequestHttpException(sprintf('Das Personenfeld "%s" ist ungültig.', $key));
        }

        return trim($value);
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function requiredString(InputBag $data, string $key, int $maxLength): string
    {
        $value = trim($data->getString($key));
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new BadRequestHttpException(sprintf('Das Feld "%s" ist erforderlich oder zu lang.', $key));
        }

        return $value;
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function nullableString(InputBag $data, string $key, int $maxLength): ?string
    {
        $value = trim($data->getString($key));
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maxLength) {
            throw new BadRequestHttpException(sprintf('Das Feld "%s" ist zu lang.', $key));
        }

        return $value;
    }
}
