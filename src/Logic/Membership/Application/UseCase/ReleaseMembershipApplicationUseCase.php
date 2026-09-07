<?php

namespace App\Logic\Membership\Application\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Application\Dto\MembershipApplicationResponse;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\Model\MembershipType;
use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\Member\Orchestrator\MemberOnboardingOrchestrator;
use App\Logic\Membership\PaymentInterval;

/**
 * Überführt einen abgeschlossenen Mitgliedsantrag in echte Mitglieder-Datensätze. Läuft
 * unabhängig vom Übertragungsstatus an das Fremdsystem (Claim/Complete/Fail/Retry), da beide
 * Vorgänge getrennt voneinander sind. Ein Antrag kann nur einmal freigegeben werden.
 *
 * Da ein Mitgliedsantrag keine Anrede erfasst, wird sie vorläufig auf „Divers“ gesetzt und muss im
 * neu angelegten Mitglied nachgepflegt werden. Bei einer Familienmitgliedschaft wird die erste
 * Person als Hauptmitglied geführt, weitere Personen ab 21 Jahren als Partner, jüngere als Kind
 * (eine im Antrag nicht erfasste Zuordnung, die später im Freigabeprozess verfeinert werden kann).
 */
readonly class ReleaseMembershipApplicationUseCase
{
    public function __construct(
        private MembershipApplicationManagerInterface $applications,
        private MemberOnboardingOrchestrator $orchestrator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id): MembershipApplicationResponse
    {
        $application = $this->applications->get($id);
        if ($application->releasedAt !== null) {
            throw new BusinessRuleViolationException('Der Mitgliedsantrag wurde bereits als Mitglied angelegt.');
        }
        $now = $this->clock->now();

        $memberIds = [];
        $headMemberNumber = null;
        $headMemberId = null;
        foreach ($application->applicants as $index => $applicant) {
            $isHead = $index === 0;
            $isFamily = $application->membershipType === MembershipType::Family;
            $age = (int) $applicant->birthDate->diff($now)->y;
            $familyRole = match (true) {
                !$isFamily => FamilyRole::None,
                $isHead => FamilyRole::Head,
                $age >= 21 => FamilyRole::Partner,
                default => FamilyRole::Child,
            };

            $member = $this->orchestrator->createFromRequest(new CreateMemberRequest(
                memberNumber: null,
                primaryMemberNumber: $isHead ? null : $headMemberNumber,
                salutation: Salutation::Diverse,
                lastName: $applicant->lastName,
                firstName: $applicant->firstName,
                birthDate: $applicant->birthDate,
                street: trim($applicant->street.' '.$applicant->houseNumber),
                postalCode: $applicant->postalCode,
                city: $applicant->city,
                email: $applicant->email,
                phone: $applicant->phone,
                familyRole: $familyRole,
                joinedAt: $now,
                leftAt: null,
                active: true,
                function: MemberFunction::Member,
                accountHolder: $application->accountHolder,
                iban: $application->iban,
                bankName: $application->bankName,
                mandateReference: null,
                paymentMethod: PaymentMethod::SepaDirectDebit,
                paymentInterval: PaymentInterval::Yearly,
                paymentDay: PaymentDay::First,
                payerType: $isHead ? PayerType::SelfPayer : PayerType::OtherMember,
                payerMemberId: $isHead ? null : $headMemberId,
                payerMemberNumber: null,
                nextBookingMonth: null,
                nextBookingYear: null,
            ));

            $memberIds[] = $member->id;
            if ($isHead) {
                $headMemberNumber = $member->memberNumber;
                $headMemberId = $member->id;
            }
        }

        return MembershipApplicationResponse::fromApplication(
            $this->applications->save($application->release($memberIds, $now)),
        );
    }
}
