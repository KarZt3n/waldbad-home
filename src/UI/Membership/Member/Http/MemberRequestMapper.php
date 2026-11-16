<?php

namespace App\UI\Membership\Member\Http;

use App\Logic\Membership\Member\Dto\AddRemarkRequest;
use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Dto\TransferMemberPayerRequest;
use App\Logic\Membership\Member\Dto\UpdateMemberRequest;
use App\Logic\Membership\Member\Dto\RestoreMemberRow;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Member\Model\ContributionCharge;
use App\Logic\Membership\Member\Model\Remark;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\PaymentInterval;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Wandelt sowohl JSON-Anfragen (Anlegen/Bearbeiten über das Overlay) als auch einzelne
 * Import-Zeilen (CSV/JSON/XML, bereits als assoziatives Array geparst) in die typisierten
 * Logic-DTOs um. Beide Wege teilen sich dieselben Feld-Validierungen.
 */
readonly class MemberRequestMapper
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): CreateMemberRequest
    {
        return new CreateMemberRequest(
            memberNumber: $this->nullableString($data, 'memberNumber', 20),
            primaryMemberNumber: $this->nullableString($data, 'primaryMemberNumber', 20),
            salutation: $this->salutation($data),
            lastName: $this->requiredString($data, 'lastName', 120),
            firstName: $this->requiredString($data, 'firstName', 120),
            birthDate: $this->requiredDate($data, 'birthDate'),
            street: $this->requiredString($data, 'street', 180),
            postalCode: $this->postalCode($data),
            city: $this->requiredString($data, 'city', 180),
            email: $this->nullableEmail($data),
            phone: $this->nullableString($data, 'phone', 60),
            familyRole: $this->familyRole($data),
            joinedAt: $this->requiredDate($data, 'joinedAt'),
            leftAt: $this->nullableDate($data, 'leftAt'),
            function: $this->memberFunction($data),
            accountHolder: $this->nullableString($data, 'accountHolder', 180),
            iban: $this->nullableString($data, 'iban', 40),
            bankName: $this->nullableString($data, 'bankName', 180),
            mandateReference: $this->nullableString($data, 'mandateReference', 60),
            paymentMethod: $this->paymentMethod($data),
            paymentInterval: $this->paymentInterval($data),
            paymentDay: $this->paymentDay($data),
            payerType: $this->payerType($data),
            payerMemberId: $this->nullableString($data, 'payerMemberId', 36),
            payerMemberNumber: $this->nullableString($data, 'payerMemberNumber', 20),
            nextBookingMonth: $this->optionalInt($data, 'nextBookingMonth', 1, 12),
            nextBookingYear: $this->optionalInt($data, 'nextBookingYear', 2000, 2200),
            contributionLiable: $this->bool($data, 'contributionLiable', true),
            mandateValidFrom: $this->nullableDate($data, 'mandateValidFrom'),
            mandateValidUntil: $this->nullableDate($data, 'mandateValidUntil'),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $id, int $version, array $data): UpdateMemberRequest
    {
        return new UpdateMemberRequest(
            id: $id,
            version: $version,
            memberNumber: $this->requiredString($data, 'memberNumber', 20),
            primaryMemberNumber: $this->requiredString($data, 'primaryMemberNumber', 20),
            salutation: $this->salutation($data),
            lastName: $this->requiredString($data, 'lastName', 120),
            firstName: $this->requiredString($data, 'firstName', 120),
            birthDate: $this->requiredDate($data, 'birthDate'),
            street: $this->requiredString($data, 'street', 180),
            postalCode: $this->postalCode($data),
            city: $this->requiredString($data, 'city', 180),
            email: $this->nullableEmail($data),
            phone: $this->nullableString($data, 'phone', 60),
            familyRole: $this->familyRole($data),
            joinedAt: $this->requiredDate($data, 'joinedAt'),
            leftAt: $this->nullableDate($data, 'leftAt'),
            function: $this->memberFunction($data),
            accountHolder: $this->nullableString($data, 'accountHolder', 180),
            iban: $this->nullableString($data, 'iban', 40),
            bankName: $this->nullableString($data, 'bankName', 180),
            mandateReference: $this->nullableString($data, 'mandateReference', 60),
            paymentMethod: $this->paymentMethod($data),
            paymentInterval: $this->paymentInterval($data),
            paymentDay: $this->paymentDay($data),
            payerType: $this->payerType($data),
            payerMemberId: $this->nullableString($data, 'payerMemberId', 36),
            nextBookingMonth: $this->optionalInt($data, 'nextBookingMonth', 1, 12),
            nextBookingYear: $this->optionalInt($data, 'nextBookingYear', 2000, 2200),
            contributionLiable: $this->bool($data, 'contributionLiable', true),
            mandateValidFrom: $this->nullableDate($data, 'mandateValidFrom'),
            mandateValidUntil: $this->nullableDate($data, 'mandateValidUntil'),
        );
    }

    public function transferPayer(string $memberId, Request $request): TransferMemberPayerRequest
    {
        $data = $request->getPayload()->all();

        return new TransferMemberPayerRequest(
            memberId: $memberId,
            successorMemberId: $this->requiredString($data, 'successorMemberId', 36),
            keepSuccessorPaymentDetails: $this->bool($data, 'keepSuccessorPaymentDetails', false),
            transferPrimaryMembership: $this->bool($data, 'transferPrimaryMembership', true),
        );
    }

    /**
     * Nachfolger beim Löschen eines Zahlers bzw. Hauptmitglieds — optional, da ein Mitglied ohne
     * Abhängige auch ohne Nachfolger gelöscht werden kann.
     *
     * @return array{successorMemberId: ?string, keepSuccessorPaymentDetails: bool}
     */
    public function deleteSuccessor(Request $request): array
    {
        $data = $request->getPayload()->all();

        return [
            'successorMemberId' => $this->nullableString($data, 'successorMemberId', 36),
            'keepSuccessorPaymentDetails' => $this->bool($data, 'keepSuccessorPaymentDetails', false),
        ];
    }

    public function addRemark(string $memberId, Request $request, ?string $authorDisplayName): AddRemarkRequest
    {
        $text = trim($request->getPayload()->getString('text'));
        if ($text === '' || mb_strlen($text) > 2000) {
            throw new BadRequestHttpException('Die Bemerkung muss zwischen 1 und 2000 Zeichen lang sein.');
        }

        return new AddRemarkRequest($memberId, $text, $authorDisplayName);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function fromImportRow(array $row): CreateMemberRequest
    {
        $memberNumber = $this->nullableString($row, 'memberNumber', 20);
        if ($memberNumber === null) {
            throw new BadRequestHttpException('Die Mitgliedsnummer ist für den Import erforderlich.');
        }

        return new CreateMemberRequest(
            memberNumber: $memberNumber,
            primaryMemberNumber: $this->nullableString($row, 'primaryMemberNumber', 20),
            salutation: $this->salutation($row),
            lastName: $this->requiredString($row, 'lastName', 120),
            firstName: $this->requiredString($row, 'firstName', 120),
            birthDate: $this->requiredDate($row, 'birthDate'),
            street: $this->requiredString($row, 'street', 180),
            postalCode: $this->postalCode($row),
            city: $this->requiredString($row, 'city', 180),
            email: $this->nullableEmail($row),
            phone: $this->nullableString($row, 'phone', 60),
            familyRole: $this->familyRole($row),
            joinedAt: $this->requiredDate($row, 'joinedAt'),
            leftAt: $this->nullableDate($row, 'leftAt'),
            function: $this->memberFunction($row),
            accountHolder: $this->nullableString($row, 'accountHolder', 180),
            iban: $this->nullableString($row, 'iban', 40),
            bankName: $this->nullableString($row, 'bankName', 180),
            mandateReference: $this->nullableString($row, 'mandateReference', 60),
            paymentMethod: $this->paymentMethod($row),
            paymentInterval: $this->paymentInterval($row),
            paymentDay: $this->paymentDay($row),
            payerType: $this->payerType($row),
            payerMemberId: $this->nullableString($row, 'payerMemberId', 36),
            payerMemberNumber: $this->nullableString($row, 'payerMemberNumber', 20),
            nextBookingMonth: $this->optionalInt($row, 'nextBookingMonth', 1, 12),
            nextBookingYear: $this->optionalInt($row, 'nextBookingYear', 2000, 2200),
            contributionLiable: $this->bool($row, 'contributionLiable', true),
            mandateValidFrom: $this->nullableDate($row, 'mandateValidFrom'),
            mandateValidUntil: $this->nullableDate($row, 'mandateValidUntil'),
        );
    }

    /**
     * Ob die Zeilen aus einem vollständigen Export stammen (mit ID und Zeitstempeln) und damit 1:1
     * wiederhergestellt werden — sonst ist es ein Abgleich-Import über die Mitgliedsnummer.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function isFullExport(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }
        foreach ($rows as $row) {
            if (($row['id'] ?? '') === '' || ($row['createdAt'] ?? '') === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function fromRestoreRow(array $row): RestoreMemberRow
    {
        $id = $this->nullableString($row, 'id', 36);
        if ($id === null || preg_match('/^[0-9a-fA-F-]{36}$/', $id) !== 1) {
            throw new BadRequestHttpException('Die ID ist ungültig.');
        }
        $data = $this->fromImportRow($row);
        $category = $this->nullableString($row, 'contributionCategory', 30);

        return new RestoreMemberRow(
            id: $id,
            memberNumber: $data->memberNumber ?? throw new BadRequestHttpException('Die Mitgliedsnummer ist für den Import erforderlich.'),
            data: $data,
            emailConsent: $this->bool($row, 'emailConsent', false),
            contributionCategory: $category === null ? null : (ContributionCategory::tryFrom($category) ?? throw new BadRequestHttpException('Die Beitragskategorie ist ungültig.')),
            contributionAmountCents: $this->optionalInt($row, 'contributionAmountCents', 0, 100_000_000),
            workAssignmentSurchargeCents: $this->optionalInt($row, 'workAssignmentSurchargeCents', 0, 100_000_000),
            remarks: array_map(
                fn (array $remark): Remark => new Remark(
                    id: $this->requiredId($remark),
                    text: $this->requiredString($remark, 'text', 2000),
                    authorDisplayName: $this->nullableString($remark, 'authorDisplayName', 180),
                    createdAt: $this->requiredDateTime($remark, 'createdAt'),
                ),
                $this->subItems($row, 'remarks'),
            ),
            oneTimeCharges: array_map(
                fn (array $charge): ContributionCharge => new ContributionCharge(
                    id: $this->requiredId($charge),
                    label: $this->requiredString($charge, 'label', 180),
                    amountCents: $this->optionalInt($charge, 'amountCents', 0, 100_000_000) ?? throw new BadRequestHttpException('Der Betrag einer Gebühr fehlt.'),
                    chargedAt: $this->requiredDateTime($charge, 'chargedAt'),
                ),
                $this->subItems($row, 'oneTimeCharges'),
            ),
            createdAt: $this->requiredDateTime($row, 'createdAt'),
            updatedAt: $this->requiredDateTime($row, 'updatedAt'),
            deletedAt: ($row['deletedAt'] ?? '') === '' ? null : $this->requiredDateTime($row, 'deletedAt'),
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<array<string, mixed>>
     */
    private function subItems(array $row, string $key): array
    {
        $items = $row[$key] ?? [];
        if (!is_array($items)) {
            throw new BadRequestHttpException(sprintf('Das Feld "%s" ist ungültig.', $key));
        }
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new BadRequestHttpException(sprintf('Das Feld "%s" ist ungültig.', $key));
            }
            $entry = [];
            foreach ($item as $itemKey => $value) {
                $entry[(string) $itemKey] = $value;
            }
            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredId(array $data): string
    {
        $id = $this->nullableString($data, 'id', 36);

        return $id ?? throw new BadRequestHttpException('Eine Bemerkung oder Gebühr hat keine ID.');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredDateTime(array $data, string $key): \DateTimeImmutable
    {
        $value = $data[$key] ?? null;
        $date = is_string($value) ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, trim($value)) : false;
        if ($date === false) {
            throw new BadRequestHttpException(sprintf('Der Zeitstempel "%s" ist ungültig.', $key));
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function salutation(array $data): Salutation
    {
        try {
            return Salutation::from($this->requiredString($data, 'salutation', 20));
        } catch (\ValueError) {
            throw new BadRequestHttpException('Die Anrede ist ungültig.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function familyRole(array $data): FamilyRole
    {
        try {
            return FamilyRole::from($this->requiredString($data, 'familyRole', 20));
        } catch (\ValueError) {
            throw new BadRequestHttpException('Die Familienzugehörigkeit ist ungültig.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function memberFunction(array $data): MemberFunction
    {
        try {
            return MemberFunction::from($this->requiredString($data, 'function', 20));
        } catch (\ValueError) {
            throw new BadRequestHttpException('Die Funktion im Verein ist ungültig.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function paymentMethod(array $data): PaymentMethod
    {
        try {
            return PaymentMethod::from($this->requiredString($data, 'paymentMethod', 20));
        } catch (\ValueError) {
            throw new BadRequestHttpException('Die Zahlart ist ungültig.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function paymentInterval(array $data): PaymentInterval
    {
        try {
            return PaymentInterval::from($this->requiredString($data, 'paymentInterval', 20));
        } catch (\ValueError) {
            throw new BadRequestHttpException('Das Zahlintervall ist ungültig.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function paymentDay(array $data): PaymentDay
    {
        try {
            return PaymentDay::from($this->requiredString($data, 'paymentDay', 20));
        } catch (\ValueError) {
            throw new BadRequestHttpException('Der Zahlungstag ist ungültig.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function payerType(array $data): PayerType
    {
        try {
            return PayerType::from($this->requiredString($data, 'payerType', 20));
        } catch (\ValueError) {
            throw new BadRequestHttpException('Die Zahlerart ist ungültig.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function postalCode(array $data): string
    {
        $value = $this->requiredString($data, 'postalCode', 5);
        if (preg_match('/^\d{5}$/', $value) !== 1) {
            throw new BadRequestHttpException('Die Postleitzahl muss aus fünf Ziffern bestehen.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function nullableEmail(array $data): ?string
    {
        $email = $this->nullableString($data, 'email', 180);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BadRequestHttpException('Die E-Mail-Adresse ist ungültig.');
        }

        return $email;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredDate(array $data, string $key): \DateTimeImmutable
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw new BadRequestHttpException(sprintf('Das Datumsfeld "%s" ist erforderlich.', $key));
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        if ($date === false || $date->format('Y-m-d') !== trim($value)) {
            throw new BadRequestHttpException(sprintf('Das Datumsfeld "%s" ist ungültig.', $key));
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function nullableDate(array $data, string $key): ?\DateTimeImmutable
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new BadRequestHttpException(sprintf('Das Datumsfeld "%s" ist ungültig.', $key));
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        if ($date === false || $date->format('Y-m-d') !== trim($value)) {
            throw new BadRequestHttpException(sprintf('Das Datumsfeld "%s" ist ungültig.', $key));
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function bool(array $data, string $key, bool $default): bool
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'ja', 'yes'], true);
        }

        return (bool) $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalInt(array $data, string $key, int $min, int $max): ?int
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_int($value) && !(is_string($value) && ctype_digit(ltrim($value, '-')))) {
            throw new BadRequestHttpException(sprintf('Das Feld "%s" muss eine ganze Zahl sein.', $key));
        }
        $intValue = (int) $value;
        if ($intValue < $min || $intValue > $max) {
            throw new BadRequestHttpException(sprintf('Das Feld "%s" liegt außerhalb des gültigen Bereichs.', $key));
        }

        return $intValue;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredString(array $data, string $key, int $maxLength): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new BadRequestHttpException(sprintf('Das Feld "%s" ist erforderlich.', $key));
        }
        $value = trim($value);
        if (mb_strlen($value) > $maxLength) {
            throw new BadRequestHttpException(sprintf('Das Feld "%s" ist zu lang.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function nullableString(array $data, string $key, int $maxLength): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || mb_strlen(trim($value)) > $maxLength) {
            throw new BadRequestHttpException(sprintf('Das Feld "%s" ist ungültig.', $key));
        }

        return trim($value);
    }
}
