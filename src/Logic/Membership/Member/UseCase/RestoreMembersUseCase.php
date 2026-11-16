<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Dto\ImportMembersResponse;
use App\Logic\Membership\Member\Dto\ImportRowError;
use App\Logic\Membership\Member\Dto\RestoreMemberRow;
use App\Logic\Membership\Member\Dto\RestoreMembersRequest;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberSnapshot;

/**
 * Stellt Mitglieder aus einem vollständigen Export (`ExportMembersQuery`) 1:1 wieder her: gleiche
 * IDs, alle Felder einschließlich Beitrag, E-Mail-Einwilligung, Bemerkungen, Einmalgebühren,
 * Zeitstempel und Soft-Delete-Markierung. Nichts wird neu berechnet oder neu vergeben. Mitglieder
 * mit derselben ID werden überschrieben, unbekannte IDs neu angelegt; Mitglieder, die nicht in der
 * Datei stehen, bleiben unverändert.
 *
 * Anders als der Abgleich-Import (`ImportMembersUseCase`) gilt: alles oder nichts. Ist eine Zeile
 * fehlerhaft, wird nichts geschrieben und alle Fehler werden gemeldet, damit kein halb
 * wiederhergestellter Stand entsteht.
 */
readonly class RestoreMembersUseCase
{
    public function __construct(private MemberManagerInterface $manager)
    {
    }

    public function execute(RestoreMembersRequest $request): ImportMembersResponse
    {
        $existingNumberById = [];
        foreach ($this->manager->findAllSnapshots() as $snapshot) {
            $existingNumberById[$snapshot->member->id] = $snapshot->member->memberNumber;
        }

        $errors = [];
        $rowByNumber = [];
        $rowById = [];
        foreach ($request->rows as $index => $row) {
            if (isset($rowById[$row->id])) {
                $errors[] = new ImportRowError($index + 1, sprintf('Die ID "%s" kommt mehrfach vor (Zeile %d).', $row->id, $rowById[$row->id]));
            }
            if (isset($rowByNumber[$row->memberNumber])) {
                $errors[] = new ImportRowError($index + 1, sprintf('Die Mitgliedsnummer "%s" kommt mehrfach vor (Zeile %d).', $row->memberNumber, $rowByNumber[$row->memberNumber]));
            }
            $rowById[$row->id] ??= $index + 1;
            $rowByNumber[$row->memberNumber] ??= $index + 1;
        }

        // Endstand: Mitglieder aus der Datei mit ihrer neuen Nummer, alle übrigen wie bisher.
        $finalNumberById = $existingNumberById;
        foreach ($request->rows as $row) {
            $finalNumberById[$row->id] = $row->memberNumber;
        }
        $idsByNumber = [];
        foreach ($finalNumberById as $id => $number) {
            $idsByNumber[$number][] = (string) $id;
        }

        $snapshots = [];
        foreach ($request->rows as $index => $row) {
            // Doppelte Nummern innerhalb der Datei sind oben schon gemeldet; hier zählen nur Mitglieder außerhalb der Datei.
            $conflicting = array_diff($idsByNumber[$row->memberNumber] ?? [], array_keys($rowById));
            if ($conflicting !== []) {
                $errors[] = new ImportRowError($index + 1, sprintf('Die Mitgliedsnummer "%s" ist bereits an ein anderes Mitglied vergeben, das nicht in der Datei steht.', $row->memberNumber));
            }
            if ($row->data->payerMemberId !== null && !isset($finalNumberById[$row->data->payerMemberId])) {
                $errors[] = new ImportRowError($index + 1, sprintf('Das zahlende Mitglied mit der ID "%s" gibt es weder in der Datei noch in der Datenbank.', $row->data->payerMemberId));
            }
            try {
                $snapshots[] = new MemberSnapshot($this->member($row), $row->createdAt, $row->updatedAt, $row->deletedAt);
            } catch (BusinessRuleViolationException $exception) {
                $errors[] = new ImportRowError($index + 1, $exception->getMessage());
            }
        }

        if ($errors !== []) {
            usort($errors, static fn (ImportRowError $left, ImportRowError $right): int => $left->rowNumber <=> $right->rowNumber);

            return new ImportMembersResponse(0, 0, $errors);
        }

        $this->manager->restoreAll($snapshots);
        $created = count(array_filter($request->rows, static fn (RestoreMemberRow $row): bool => !isset($existingNumberById[$row->id])));

        return new ImportMembersResponse($created, count($request->rows) - $created, []);
    }

    private function member(RestoreMemberRow $row): Member
    {
        $data = $row->data;

        return new Member(
            id: $row->id,
            memberNumber: $row->memberNumber,
            primaryMemberNumber: $data->primaryMemberNumber ?? $row->memberNumber,
            salutation: $data->salutation,
            lastName: $data->lastName,
            firstName: $data->firstName,
            birthDate: $data->birthDate,
            street: $data->street,
            postalCode: $data->postalCode,
            city: $data->city,
            email: $data->email,
            phone: $data->phone,
            familyRole: $data->familyRole,
            joinedAt: $data->joinedAt,
            leftAt: $data->leftAt,
            function: $data->function,
            accountHolder: $data->accountHolder,
            iban: $data->iban,
            bankName: $data->bankName,
            mandateReference: $data->mandateReference,
            paymentMethod: $data->paymentMethod,
            paymentInterval: $data->paymentInterval,
            paymentDay: $data->paymentDay,
            payerType: $data->payerType,
            payerMemberId: $data->payerMemberId,
            nextBookingMonth: $data->nextBookingMonth,
            nextBookingYear: $data->nextBookingYear,
            contributionCategory: $row->contributionCategory,
            contributionAmountCents: $row->contributionAmountCents,
            workAssignmentSurchargeCents: $row->workAssignmentSurchargeCents,
            remarks: $row->remarks,
            oneTimeCharges: $row->oneTimeCharges,
            version: 0,
            contributionLiable: $data->contributionLiable,
            mandateValidFrom: $data->mandateValidFrom,
            mandateValidUntil: $data->mandateValidUntil,
            emailConsent: $row->emailConsent,
        );
    }
}
