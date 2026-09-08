<?php

namespace App\UI\Membership\Member\Http;

/**
 * Wandelt die bereits über `MemberResponseFactory` aufbereiteten Mitglieder-Zeilen in CSV-, JSON-
 * oder XML-Text um. CSV ist naturgemäß flach — Bemerkungen werden dort zu einer einzelnen Spalte
 * zusammengefasst, JSON und XML behalten die volle, verschachtelte Struktur.
 */
readonly class MemberExportFormatter
{
    /**
     * @var list<string>
     */
    private const array COLUMNS = [
        'memberNumber', 'primaryMemberNumber', 'salutation', 'lastName', 'firstName', 'birthDate',
        'street', 'postalCode', 'city', 'email', 'phone', 'familyRole', 'joinedAt', 'leftAt',
        'active', 'function', 'contributionLiable', 'accountHolder', 'iban', 'bankName', 'mandateReference',
        'mandateValidFrom', 'mandateValidUntil',
        'paymentMethod', 'paymentInterval', 'paymentDay', 'payerType', 'payerMemberId',
        'payerDisplayName', 'nextBookingMonth', 'nextBookingYear', 'contributionCategory',
        'contributionAmountCents', 'workAssignmentSurchargeCents',
    ];

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Der CSV-Export konnte nicht erstellt werden.');
        }
        $columns = [...self::COLUMNS, 'remarks', 'oneTimeCharges'];
        fputcsv($handle, $columns, ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $column): string => $this->csvValue($row, $column), $columns), ';', '"', '\\');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function toJson(array $rows): string
    {
        return (string) json_encode(['members' => $rows], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function toXml(array $rows): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElement('members');
        $document->appendChild($root);

        foreach ($rows as $row) {
            $memberElement = $document->createElement('member');
            foreach (self::COLUMNS as $column) {
                $memberElement->appendChild($document->createElement($column, $this->xmlScalar($row[$column] ?? null)));
            }
            $memberElement->appendChild($this->remarksElement($document, $this->extractSubItems($row, 'remarks')));
            $memberElement->appendChild($this->chargesElement($document, $this->extractSubItems($row, 'oneTimeCharges')));
            $root->appendChild($memberElement);
        }

        $xml = $document->saveXML();

        return $xml === false ? '' : $xml;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function csvValue(array $row, string $column): string
    {
        if ($column === 'remarks') {
            return implode(' | ', array_map(
                static fn (array $remark): string => sprintf(
                    '%s: %s',
                    is_scalar($remark['createdAt'] ?? null) ? (string) $remark['createdAt'] : '',
                    is_scalar($remark['text'] ?? null) ? (string) $remark['text'] : '',
                ),
                $this->extractSubItems($row, 'remarks'),
            ));
        }
        if ($column === 'oneTimeCharges') {
            return implode(' | ', array_map(
                static fn (array $charge): string => sprintf(
                    '%s: %s (%s)',
                    is_scalar($charge['chargedAt'] ?? null) ? (string) $charge['chargedAt'] : '',
                    is_scalar($charge['label'] ?? null) ? (string) $charge['label'] : '',
                    is_scalar($charge['amountCents'] ?? null) ? (string) $charge['amountCents'] : '',
                ),
                $this->extractSubItems($row, 'oneTimeCharges'),
            ));
        }

        return $this->xmlScalar($row[$column] ?? null);
    }

    private function xmlScalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $row
     * @return list<array<array-key, mixed>>
     */
    private function extractSubItems(array $row, string $key): array
    {
        $items = $row[$key] ?? null;
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param list<array<array-key, mixed>> $remarks
     */
    private function remarksElement(\DOMDocument $document, array $remarks): \DOMElement
    {
        $remarksElement = $document->createElement('remarks');
        foreach ($remarks as $remark) {
            $remarkElement = $document->createElement('remark');
            $remarkElement->appendChild($document->createElement('text', $this->xmlScalar($remark['text'] ?? null)));
            $remarkElement->appendChild($document->createElement('createdAt', $this->xmlScalar($remark['createdAt'] ?? null)));
            $remarkElement->appendChild($document->createElement('authorDisplayName', $this->xmlScalar($remark['authorDisplayName'] ?? null)));
            $remarksElement->appendChild($remarkElement);
        }

        return $remarksElement;
    }

    /**
     * @param list<array<array-key, mixed>> $charges
     */
    private function chargesElement(\DOMDocument $document, array $charges): \DOMElement
    {
        $chargesElement = $document->createElement('oneTimeCharges');
        foreach ($charges as $charge) {
            $chargeElement = $document->createElement('charge');
            $chargeElement->appendChild($document->createElement('label', $this->xmlScalar($charge['label'] ?? null)));
            $chargeElement->appendChild($document->createElement('amountCents', $this->xmlScalar($charge['amountCents'] ?? null)));
            $chargeElement->appendChild($document->createElement('chargedAt', $this->xmlScalar($charge['chargedAt'] ?? null)));
            $chargesElement->appendChild($chargeElement);
        }

        return $chargesElement;
    }
}
