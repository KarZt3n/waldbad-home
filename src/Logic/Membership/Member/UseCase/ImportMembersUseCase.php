<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Dto\ImportMembersRequest;
use App\Logic\Membership\Member\Dto\ImportMembersResponse;
use App\Logic\Membership\Member\Dto\ImportRowError;
use App\Logic\Membership\Member\Dto\UpdateMemberRequest;
use App\Logic\Membership\Member\Mapping\MemberModelFactory;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Orchestrator\MemberOnboardingOrchestrator;

/**
 * Importiert Mitglieder aus CSV/JSON/XML. Jede Zeile trägt bereits eine feste Mitgliedsnummer aus
 * der bisherigen Verwaltung; existiert dazu bereits ein Mitglied, wird es aktualisiert, ansonsten
 * neu angelegt. Fehlerhafte Zeilen brechen den Import nicht ab, sondern werden gesammelt
 * zurückgegeben, damit ein einzelner fehlerhafter Datensatz nicht den gesamten Import verhindert.
 */
readonly class ImportMembersUseCase
{
    public function __construct(
        private MemberManagerInterface $manager,
        private MemberOnboardingOrchestrator $orchestrator,
        private MemberModelFactory $factory,
    ) {
    }

    public function execute(ImportMembersRequest $request): ImportMembersResponse
    {
        $created = 0;
        $updated = 0;
        $errors = [];

        /** @var array<string, true> $knownMemberNumbers Jede Nummer aus der Importdatei gilt als
         *  "wird existieren", damit ein Hauptmitglied, das erst später im gleichen Lauf angelegt
         *  wird, dessen Familienmitglieder nicht blockiert (siehe orderForProcessing()). */
        $knownMemberNumbers = [];
        foreach ($request->rows as $row) {
            if ($row->memberNumber !== null) {
                $knownMemberNumbers[$row->memberNumber] = true;
            }
        }

        foreach ($this->orderForProcessing($request->rows) as $index) {
            $row = $request->rows[$index];
            try {
                if ($this->importRow($row, $knownMemberNumbers) === 'created') {
                    ++$created;
                } else {
                    ++$updated;
                }
            } catch (BusinessRuleViolationException $exception) {
                $errors[] = new ImportRowError($index + 1, $exception->getMessage());
            }
        }

        return new ImportMembersResponse($created, $updated, $errors);
    }

    /**
     * @param array<string, true> $knownMemberNumbers
     *
     * @return 'created'|'updated'
     */
    private function importRow(CreateMemberRequest $row, array $knownMemberNumbers): string
    {
        $existing = $row->memberNumber === null ? null : $this->manager->findByMemberNumber($row->memberNumber);
        if ($existing === null) {
            $this->orchestrator->createFromRequest($row, chargeOneTimeFees: false, knownMemberNumbers: $knownMemberNumbers);

            return 'created';
        }

        $member = $this->factory->rebuildFromRequest($this->toUpdateRequest($row, $existing), $existing);
        $this->manager->save($member);

        return 'updated';
    }

    private function toUpdateRequest(CreateMemberRequest $row, Member $existing): UpdateMemberRequest
    {
        $memberNumber = $row->memberNumber ?? $existing->memberNumber;

        $payerMemberId = $row->payerMemberId;
        if ($row->payerMemberNumber !== null) {
            $payerMemberId = ($this->manager->findByMemberNumber($row->payerMemberNumber)
                ?? throw new BusinessRuleViolationException(sprintf(
                    'Es wurde kein zahlendes Mitglied mit der Mitgliedsnummer "%s" gefunden.',
                    $row->payerMemberNumber,
                )))->id;
        }

        return new UpdateMemberRequest(
            id: $existing->id,
            version: $existing->version,
            memberNumber: $memberNumber,
            primaryMemberNumber: $row->primaryMemberNumber ?? $memberNumber,
            salutation: $row->salutation,
            lastName: $row->lastName,
            firstName: $row->firstName,
            birthDate: $row->birthDate,
            street: $row->street,
            postalCode: $row->postalCode,
            city: $row->city,
            email: $row->email,
            phone: $row->phone,
            familyRole: $row->familyRole,
            joinedAt: $row->joinedAt,
            leftAt: $row->leftAt,
            active: $row->active,
            function: $row->function,
            accountHolder: $row->accountHolder,
            iban: $row->iban,
            bankName: $row->bankName,
            mandateReference: $row->mandateReference ?? $memberNumber,
            paymentMethod: $row->paymentMethod,
            paymentInterval: $row->paymentInterval,
            paymentDay: $row->paymentDay,
            payerType: $row->payerType,
            payerMemberId: $payerMemberId,
            nextBookingMonth: $row->nextBookingMonth ?? 3,
            nextBookingYear: $row->nextBookingYear ?? ((int) $row->joinedAt->format('Y') + 1),
            contributionLiable: $row->contributionLiable,
        );
    }

    /**
     * Zeilen können sich gegenseitig referenzieren (z. B. zahlt ein Familienmitglied für ein
     * anderes, dessen Zeile erst weiter hinten in der Import-Datei steht). Verweist eine neu
     * anzulegende Zeile auf ein ebenfalls neu anzulegendes zahlendes Mitglied, muss dessen Zeile
     * zuerst verarbeitet werden, sonst schlägt das Anlegen mit einer Fremdschlüsselverletzung fehl.
     * Diese topologische Sortierung stellt das sicher, unabhängig von der Reihenfolge in der
     * Importdatei — analog zu ImportSageGsMembersUseCase::orderForInsertion().
     *
     * @param list<CreateMemberRequest> $rows
     *
     * @return list<int>
     */
    private function orderForProcessing(array $rows): array
    {
        $positionByNumber = [];
        foreach ($rows as $position => $row) {
            if ($row->memberNumber !== null) {
                $positionByNumber[$row->memberNumber] = $position;
            }
        }

        $dependents = [];
        $indegree = [];
        foreach (array_keys($rows) as $position) {
            $indegree[$position] = 0;
        }
        foreach ($rows as $position => $row) {
            $payerPosition = $row->payerMemberNumber !== null ? ($positionByNumber[$row->payerMemberNumber] ?? null) : null;
            if ($payerPosition !== null && $payerPosition !== $position) {
                $dependents[$payerPosition][] = $position;
                ++$indegree[$position];
            }
        }

        $ready = [];
        foreach ($indegree as $position => $degree) {
            if ($degree === 0) {
                $ready[] = $position;
            }
        }
        sort($ready);
        $ordered = [];
        while ($ready !== []) {
            $current = array_shift($ready);
            $ordered[] = $current;
            foreach ($dependents[$current] ?? [] as $dependent) {
                if (--$indegree[$dependent] === 0) {
                    $ready[] = $dependent;
                }
            }
            sort($ready);
        }

        // Bei einer zyklischen Abhängigkeit (z. B. zwei Mitglieder zahlen wechselseitig
        // füreinander) bleiben Positionen übrig; sie werden angehängt und scheitern dann regulär
        // an der Geschäftsregel, statt den Import zu verwerfen.
        $remaining = array_diff(array_keys($rows), $ordered);
        sort($remaining);

        return [...$ordered, ...$remaining];
    }
}
