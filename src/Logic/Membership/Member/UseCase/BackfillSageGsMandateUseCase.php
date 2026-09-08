<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Membership\Member\Dto\MandateBackfillResult;
use App\Logic\Membership\Member\Dto\MandateBackfillRow;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\MemberImportTransactionInterface;

/**
 * Ergänzt bei bereits vorhandenen Mitgliedern (Zuordnung über die Mitgliedsnummer) die
 * Mandatsreferenz sowie Mandatsgültigkeit von/bis aus einer nachträglich bereitgestellten
 * Sage-GS-Exportdatei — z. B. weil diese Felder beim ursprünglichen Bestandsimport noch nicht
 * übernommen wurden. Anders als `ImportSageGsMembersUseCase` legt dieser UseCase keine neuen
 * Mitglieder an; unbekannte Mitgliedsnummern werden nur gezählt, nicht als Fehler gemeldet.
 */
readonly class BackfillSageGsMandateUseCase
{
    public function __construct(
        private MemberManagerInterface $members,
        private MemberImportTransactionInterface $transaction,
    ) {
    }

    /**
     * @param list<MandateBackfillRow> $rows
     */
    public function execute(array $rows, bool $execute): MandateBackfillResult
    {
        $updated = 0;
        $unchanged = 0;
        $notFound = 0;
        $changed = [];
        foreach ($rows as $row) {
            $member = $this->members->findByMemberNumber($row->memberNumber);
            if ($member === null) {
                ++$notFound;
                continue;
            }
            $reference = $row->mandateReference ?? $member->mandateReference;
            $validFrom = $row->mandateValidFrom ?? $member->mandateValidFrom;
            $validUntil = $row->mandateValidUntil ?? $member->mandateValidUntil;
            if ($reference === $member->mandateReference && $validFrom == $member->mandateValidFrom && $validUntil == $member->mandateValidUntil) {
                ++$unchanged;
                continue;
            }
            $changed[] = $member->withMandate($reference, $validFrom, $validUntil);
            ++$updated;
        }

        if ($execute && $changed !== []) {
            $this->transaction->execute(function () use ($changed): void {
                foreach ($changed as $member) {
                    $this->members->save($member);
                }
            });
        }

        return new MandateBackfillResult($updated, $unchanged, $notFound);
    }
}
