<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Dto\ImportRowError;
use App\Logic\Membership\Member\Dto\SageGsImportRequest;
use App\Logic\Membership\Member\Dto\SageGsImportResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Mapping\MemberModelFactory;
use App\Logic\Membership\Member\MemberImportTransactionInterface;

final readonly class ImportSageGsMembersUseCase
{
    public function __construct(
        private MemberManagerInterface $members,
        private IdentifierGeneratorInterface $ids,
        private MemberModelFactory $factory,
        private MemberImportTransactionInterface $transaction,
    ) {}

    public function execute(SageGsImportRequest $request): SageGsImportResponse
    {
        $ids = [];
        $existing = [];
        $errors = [];
        foreach ($request->rows as $position => $row) {
            $number = $row->memberNumber;
            if ($number === null || isset($ids[$number])) {
                $errors[] = new ImportRowError($position, 'Fehlende oder doppelte Mitgliedsnummer.');
                continue;
            }
            $member = $this->members->findByMemberNumber($number);
            $ids[$number] = $member->id ?? $this->ids->generate();
            if ($member !== null) {
                $existing[$number] = true;
            }
        }
        $models = [];
        foreach ($this->orderForInsertion($request->rows, $existing) as $position) {
            $row = $request->rows[$position];
            $number = $row->memberNumber;
            if ($number === null) {
                continue; // orderForInsertion() liefert nur Positionen mit gesetzter Mitgliedsnummer
            }
            try {
                $primary = $row->primaryMemberNumber ?? $number;
                if (!isset($ids[$primary]) && $this->members->findByMemberNumber($primary) === null) {
                    throw new BusinessRuleViolationException('Hauptmitglied fehlt.');
                }
                $payerId = $row->payerMemberId;
                if ($row->payerMemberNumber !== null) {
                    $payerId = $ids[$row->payerMemberNumber] ?? $this->members->findByMemberNumber($row->payerMemberNumber)?->id;
                    if ($payerId === null) {
                        throw new BusinessRuleViolationException('Zahlendes Mitglied fehlt.');
                    }
                }
                $models[] = $this->factory->createFromRequest(
                    $row, $ids[$number], $number, $primary, $row->mandateReference ?? $number,
                    $payerId, $row->nextBookingMonth ?? 3,
                    $row->nextBookingYear ?? ((int) $row->joinedAt->format('Y') + 1),
                );
            } catch (BusinessRuleViolationException $exception) {
                $errors[] = new ImportRowError($position, $exception->getMessage());
            }
        }
        if ($errors === [] && $request->execute) {
            $this->transaction->execute(function () use ($models): void {
                foreach ($models as $model) {
                    $this->members->save($model);
                }
            });
        }

        return new SageGsImportResponse(count($models), count($existing), $errors);
    }

    /**
     * Jede save()-Operation flusht sofort (siehe DoctrineMemberProcessor::create()), und
     * payer_member_id ist ein Fremdschlüssel auf member.id. Verweist eine neu anzulegende Zeile auf
     * ein ebenfalls neu anzulegendes zahlendes Mitglied, muss dessen Zeile zuerst eingefügt werden,
     * sonst schlägt der Insert mit einer Fremdschlüsselverletzung fehl. Diese topologische
     * Sortierung stellt das sicher, unabhängig von der Reihenfolge in der Importdatei.
     *
     * @param array<int, CreateMemberRequest> $rows
     * @param array<string, true> $existing
     *
     * @return list<int>
     */
    private function orderForInsertion(array $rows, array $existing): array
    {
        $candidates = [];
        $positionByNumber = [];
        foreach ($rows as $position => $row) {
            if ($row->memberNumber !== null && !isset($existing[$row->memberNumber])) {
                $candidates[$position] = $row;
                $positionByNumber[$row->memberNumber] = $position;
            }
        }

        $dependents = [];
        $indegree = [];
        foreach (array_keys($candidates) as $position) {
            $indegree[$position] = 0;
        }
        foreach ($candidates as $position => $row) {
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
        // an der Fremdschlüsselprüfung bzw. der Geschäftsregel, statt den Import zu verwerfen.
        $remaining = array_diff(array_keys($candidates), $ordered);
        sort($remaining);

        return [...$ordered, ...$remaining];
    }
}
