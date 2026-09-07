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

        foreach ($request->rows as $index => $row) {
            try {
                if ($this->importRow($row) === 'created') {
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
     * @return 'created'|'updated'
     */
    private function importRow(CreateMemberRequest $row): string
    {
        $existing = $row->memberNumber === null ? null : $this->manager->findByMemberNumber($row->memberNumber);
        if ($existing === null) {
            $this->orchestrator->createFromRequest($row, chargeOneTimeFees: false);

            return 'created';
        }

        $member = $this->factory->rebuildFromRequest($this->toUpdateRequest($row, $existing), $existing);
        $this->manager->save($member);

        return 'updated';
    }

    private function toUpdateRequest(CreateMemberRequest $row, Member $existing): UpdateMemberRequest
    {
        $memberNumber = $row->memberNumber ?? $existing->memberNumber;

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
            payerMemberId: $row->payerMemberId,
            nextBookingMonth: $row->nextBookingMonth ?? 3,
            nextBookingYear: $row->nextBookingYear ?? ((int) $row->joinedAt->format('Y') + 1),
        );
    }
}
