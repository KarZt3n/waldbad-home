<?php

namespace App\UI\Membership\Member\Cli;

use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\UI\Membership\Member\Http\MemberRequestMapper;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class SageGsRowMapper
{
    public function __construct(private MemberRequestMapper $mapper) {}

    /**
     * @param array<string, string> $row
     * @param array<string, scalar|null> $overrides
     */
    public function map(array $row, array $overrides = [], bool $familyPayer = false): CreateMemberRequest
    {
        $data = [];
        foreach ([
            'MITNUM' => 'memberNumber', 'FANUM' => 'primaryMemberNumber',
            'NAME' => 'lastName', 'VORNAME' => 'firstName', 'STRASSE' => 'street',
            'PLZ' => 'postalCode', 'ORT' => 'city', 'EMAIL' => 'email',
            'KTOINHABER' => 'accountHolder', 'IBAN' => 'iban', 'BANK' => 'bankName',
            'MANDATSREFERENZ' => 'mandateReference', 'ZAHLERNR' => 'payerMemberNumber',
        ] as $source => $target) {
            $data[$target] = ($row[$source] ?? '') === '' ? null : $row[$source];
        }
        $data['phone'] = ($row['TELEFON'] ?? '') ?: (($row['MOBIL'] ?? '') ?: null);
        $data['iban'] = isset($data['iban']) ? strtoupper(str_replace(' ', '', $data['iban'])) : null;
        foreach (['GEBURT' => 'birthDate', 'MITSEIT' => 'joinedAt', 'AUSTRITT' => 'leftAt'] as $source => $target) {
            if (array_key_exists($target, $overrides)) {
                continue;
            }
            $value = $row[$source] ?? '';
            $date = \DateTimeImmutable::createFromFormat('!d.m.Y', $value);
            if ($value !== '' && ($date === false || $date->format('d.m.Y') !== $value)) {
                throw new BadRequestHttpException('Ungültiges Datumsformat in '.$source.'.');
            }
            $data[$target] = $date === false ? null : $date->format('Y-m-d');
        }
        $data['salutation'] = match (mb_strtolower($row['ANREDE'] ?? '')) {
            'herr' => 'mr', 'frau' => 'ms', default => null,
        };
        $data['function'] = match (mb_strtolower($row['FUNKTION'] ?? '')) {
            '', 'mitglied' => 'member', 'vorstand' => 'board', default => null,
        };
        $data['paymentMethod'] = match (mb_strtolower($row['ZAHLART'] ?? '')) {
            'bankeinzug' => 'sepa_direct_debit', 'überweisung' => 'bank_transfer', 'bar' => 'cash', default => null,
        };
        $data['paymentInterval'] = match (mb_strtolower($row['ZAHLWEISE'] ?? '')) {
            'jährlich' => 'yearly', 'halbjährlich' => 'half_yearly', 'vierteljährlich' => 'quarterly', 'monatlich' => 'monthly', default => null,
        };
        $data['active'] = $this->boolean($row['AKTIV'] ?? '') && ($data['leftAt'] ?? null) === null;
        $data['paymentDay'] = $this->boolean($row['ZAHLANFANG'] ?? '') ? 'first' : 'fifteenth';
        $otherPayer = $this->boolean($row['ZAHLFREMD'] ?? '');
        $data['payerType'] = $otherPayer ? 'other_member' : 'self_payer';
        if ($otherPayer && $data['payerMemberNumber'] === null && $familyPayer) {
            $data['payerMemberNumber'] = $data['primaryMemberNumber'];
        }
        if (!$otherPayer) {
            $data['payerMemberNumber'] = null;
        }
        $primary = $data['primaryMemberNumber'];
        $data['familyRole'] = empty($primary) ? 'none' : ($primary === $data['memberNumber'] ? 'head' : null);
        $data['nextBookingMonth'] = $row['NM'] ?? null;
        $year = $row['NJ'] ?? '';
        $data['nextBookingYear'] = preg_match('/^\d{2}$/', $year) === 1 ? 2000 + (int) $year : $year;
        foreach (array_keys($overrides) as $field) {
            if (!array_key_exists($field, $data) && !in_array($field, ['birthDate', 'joinedAt', 'leftAt'], true)) {
                throw new BadRequestHttpException('Mapping enthält ein unbekanntes Zielfeld.');
            }
        }
        $data = array_replace($data, $overrides);
        // Nach den Korrekturen erneut normalisieren: Ein Selbstzahler darf kein zahlendes Mitglied
        // tragen (sonst schlägt die Geschäftsregel im Member-Modell fehl), und ein abweichender
        // Zahler benötigt keine eigenen Bankdaten. Ohne diese erneute Normalisierung könnte eine
        // Korrekturdatei, die nur `payerType` überschreibt, einen widersprüchlichen Datensatz
        // erzeugen (z. B. `self_payer` mit einer aus dem FANUM-Fallback stammenden, jetzt
        // veralteten `payerMemberNumber`).
        if ($data['payerType'] === 'self_payer') {
            $data['payerMemberNumber'] = null;
            if ($data['accountHolder'] === null) {
                // Sage trägt KTOINHABER nur ein, wenn er vom Mitglied abweicht; ist das Konto auf
                // das Mitglied selbst geführt, bleibt das Feld leer. Analog zur bereits
                // bestehenden MANDATSREFERENZ-Regel (leer -> Mitgliedsnummer) wird hier auf den
                // eigenen Namen defaultet, statt einen Selbstzahler ohne erkennbaren Grund
                // abzulehnen.
                $data['accountHolder'] = trim(($data['firstName'] ?? '').' '.($data['lastName'] ?? ''));
            }
        } else {
            $data['accountHolder'] = null;
            $data['iban'] = null;
            $data['bankName'] = null;
            $data['mandateReference'] = null;
        }
        if ($data['familyRole'] === null) {
            throw new BadRequestHttpException('Familienrolle partner/child muss in der Mapping-Datei festgelegt werden.');
        }
        if ($data['payerType'] === 'other_member' && empty($data['payerMemberNumber'])) {
            throw new BadRequestHttpException('Abweichender Zahler ohne ZAHLERNR; Zuordnung erforderlich.');
        }
        if (empty($data['nextBookingMonth']) || empty($data['nextBookingYear'])) {
            throw new BadRequestHttpException('Nächster Buchungstermin NM/NJ fehlt; Zuordnung erforderlich.');
        }

        return $this->mapper->fromImportRow($data);
    }

    private function boolean(string $value): bool
    {
        return match (mb_strtolower($value)) {
            'wahr' => true, 'falsch' => false,
            default => throw new BadRequestHttpException('Ungültiger Sage-Wahrheitswert.'),
        };
    }
}
