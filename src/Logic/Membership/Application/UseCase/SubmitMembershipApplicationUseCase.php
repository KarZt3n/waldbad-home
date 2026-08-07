<?php

namespace App\Logic\Membership\Application\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\Application\Dto\MembershipApplicationResponse;
use App\Logic\Membership\Application\Dto\SubmitMembershipApplicationRequest;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\Model\Applicant;
use App\Logic\Membership\Application\Model\ApplicationStatus;
use App\Logic\Membership\Application\Model\MembershipApplication;

readonly class SubmitMembershipApplicationUseCase
{
    public function __construct(
        private MembershipApplicationManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(SubmitMembershipApplicationRequest $request): MembershipApplicationResponse
    {
        $now = $this->clock->now();
        $applicants = [];
        foreach ($request->applicants as $position => $applicant) {
            $applicants[] = new Applicant(
                id: $this->identifierGenerator->generate(),
                position: $position,
                firstName: trim($applicant->firstName),
                lastName: trim($applicant->lastName),
                birthDate: $applicant->birthDate,
                street: trim($applicant->street),
                houseNumber: trim($applicant->houseNumber),
                postalCode: trim($applicant->postalCode),
                city: trim($applicant->city),
                phone: $applicant->phone === null ? null : trim($applicant->phone),
                email: $applicant->email === null ? null : mb_strtolower(trim($applicant->email)),
            );
        }
        $application = new MembershipApplication(
            id: $this->identifierGenerator->generate(),
            membershipType: $request->membershipType,
            applicants: $applicants,
            accountHolder: trim($request->accountHolder),
            iban: strtoupper((string) preg_replace('/\s+/', '', $request->iban)),
            bankName: $request->bankName === null ? null : trim($request->bankName),
            signerName: trim($request->signerName),
            emailConsent: $request->emailConsent,
            declarationVersion: trim($request->declarationVersion),
            status: ApplicationStatus::Pending,
            externalReference: null,
            failureReason: null,
            version: 0,
            submittedAt: $now,
            updatedAt: $now,
            processingAt: null,
            completedAt: null,
        );

        return MembershipApplicationResponse::fromApplication($this->manager->save($application));
    }
}
