<?php

namespace App\UI\Membership\Application\Http;

use App\Logic\Membership\Application\Dto\ApplicantInput;
use App\Logic\Membership\Application\Dto\SubmitMembershipApplicationRequest;
use App\Logic\Membership\Application\Model\MembershipType;
use App\Logic\Membership\Member\Model\Salutation;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class MembershipApplicationRequestMapper
{
    public function submit(Request $request): SubmitMembershipApplicationRequest
    {
        $data = $request->getPayload();
        if (!$data->getBoolean('privacyAccepted') || !$data->getBoolean('termsAccepted') || !$data->getBoolean('sepaAccepted')) {
            throw new BadRequestHttpException('Datenschutz, Satzung/Beitragsordnung und SEPA-Ermächtigung müssen bestätigt werden.');
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
                salutation: $this->applicantSalutation($rawApplicant),
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

        // Die Art der Mitgliedschaft wird nicht vom Formular übernommen, sondern eindeutig aus der
        // Personenzahl abgeleitet: eine Person = Einzelmitgliedschaft, mehrere = Familienmitgliedschaft.
        $membershipType = count($applicants) === 1 ? MembershipType::Individual : MembershipType::Family;

        return new SubmitMembershipApplicationRequest(
            membershipType: $membershipType,
            applicants: $applicants,
            accountHolder: $this->requiredString($data, 'accountHolder', 180),
            iban: $this->requiredString($data, 'iban', 40),
            bankName: $this->nullableString($data, 'bankName', 180),
            signerName: $this->requiredString($data, 'signerName', 180),
            emailConsent: $data->getBoolean('emailConsent'),
            declarationVersion: $this->requiredString($data, 'declarationVersion', 40),
        );
    }

    /**
     * Auswahl aus dem Abgleich „Bereits Mitglied“: `{"keep": {"applicants": {"0": ["street"]}, "bank": ["iban"]}}`
     * nennt die Felder, für die der bisherige Wert bleibt. Ohne Rumpf gilt überall der Antrag.
     * `{"leave": ["<memberId>"]}` nennt im Antrag fehlende Haushaltsmitglieder, deren Austritt eingetragen wird.
     *
     * @return array{0: array<int, list<string>>, 1: list<string>, 2: list<string>}
     */
    public function mergeChoices(Request $request): array
    {
        if ($request->getContent() === '') {
            return [[], [], []];
        }
        $keep = $request->getPayload()->all('keep');
        $applicants = [];
        foreach ($this->arrayValue($keep, 'applicants') as $position => $fields) {
            if (!is_int($position) && !ctype_digit($position)) {
                throw new BadRequestHttpException('Die Personenposition ist ungültig.');
            }
            $applicants[(int) $position] = $this->stringList($fields);
        }

        return [$applicants, $this->stringList($keep['bank'] ?? []), $this->stringList($request->getPayload()->all('leave'))];
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private function arrayValue(array $data, string $key): array
    {
        $value = $data[$key] ?? [];
        if (!is_array($value)) {
            throw new BadRequestHttpException(sprintf('"%s" muss ein Objekt sein.', $key));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new BadRequestHttpException('Die Auswahl muss als Liste übermittelt werden.');
        }
        $fields = [];
        foreach ($value as $field) {
            if (!is_string($field)) {
                throw new BadRequestHttpException('Die Auswahl muss aus Texten bestehen.');
            }
            $fields[] = $field;
        }

        return $fields;
    }

    public function reject(Request $request): ?string
    {
        return $this->nullableString($request->getPayload(), 'reason', 500);
    }

    /**
     * @param array<mixed> $data
     */
    private function applicantSalutation(array $data): Salutation
    {
        $value = $data['salutation'] ?? null;
        if (!is_string($value)) {
            throw new BadRequestHttpException('Die Anrede ist für jede Person erforderlich.');
        }
        try {
            return Salutation::from($value);
        } catch (\ValueError) {
            throw new BadRequestHttpException('Die Anrede ist ungültig.');
        }
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
