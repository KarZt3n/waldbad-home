<?php

namespace App\Data\Membership\Application\Mapper;

use App\Data\Membership\Application\Entity\MembershipApplicantEntity;
use App\Data\Membership\Application\Entity\MembershipApplicationEntity;
use App\Logic\Membership\Application\Model\Applicant;
use App\Logic\Membership\Application\Model\ApplicationStatus;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Membership\Application\Model\MembershipType;

readonly class MembershipApplicationMapper
{
    public function toModel(MembershipApplicationEntity $entity): MembershipApplication
    {
        return new MembershipApplication(
            id: $entity->getId(),
            membershipType: MembershipType::from($entity->getMembershipType()),
            applicants: array_map(
                static fn (MembershipApplicantEntity $applicant): Applicant => new Applicant(
                    id: $applicant->getId(),
                    position: $applicant->getPosition(),
                    firstName: $applicant->getFirstName(),
                    lastName: $applicant->getLastName(),
                    birthDate: $applicant->getBirthDate(),
                    street: $applicant->getStreet(),
                    houseNumber: $applicant->getHouseNumber(),
                    postalCode: $applicant->getPostalCode(),
                    city: $applicant->getCity(),
                    phone: $applicant->getPhone(),
                    email: $applicant->getEmail(),
                ),
                $entity->getApplicants(),
            ),
            accountHolder: $entity->getAccountHolder(),
            iban: $entity->getIban(),
            bankName: $entity->getBankName(),
            signerName: $entity->getSignerName(),
            emailConsent: $entity->hasEmailConsent(),
            declarationVersion: $entity->getDeclarationVersion(),
            status: ApplicationStatus::from($entity->getStatus()),
            externalReference: $entity->getExternalReference(),
            failureReason: $entity->getFailureReason(),
            version: $entity->getVersion(),
            submittedAt: $entity->getSubmittedAt(),
            updatedAt: $entity->getUpdatedAt(),
            processingAt: $entity->getProcessingAt(),
            completedAt: $entity->getCompletedAt(),
        );
    }

    public function createEntity(MembershipApplication $application): MembershipApplicationEntity
    {
        $entity = new MembershipApplicationEntity(
            id: $application->id,
            membershipType: $application->membershipType->value,
            accountHolder: $application->accountHolder,
            iban: $application->iban,
            bankName: $application->bankName,
            signerName: $application->signerName,
            emailConsent: $application->emailConsent,
            declarationVersion: $application->declarationVersion,
            status: $application->status->value,
            externalReference: $application->externalReference,
            failureReason: $application->failureReason,
            submittedAt: $application->submittedAt,
            updatedAt: $application->updatedAt,
            processingAt: $application->processingAt,
            completedAt: $application->completedAt,
        );
        foreach ($application->applicants as $applicant) {
            $entity->addApplicant(new MembershipApplicantEntity(
                id: $applicant->id,
                application: $entity,
                position: $applicant->position,
                firstName: $applicant->firstName,
                lastName: $applicant->lastName,
                birthDate: $applicant->birthDate,
                street: $applicant->street,
                houseNumber: $applicant->houseNumber,
                postalCode: $applicant->postalCode,
                city: $applicant->city,
                phone: $applicant->phone,
                email: $applicant->email,
            ));
        }

        return $entity;
    }

    public function updateEntity(MembershipApplication $application, MembershipApplicationEntity $entity): void
    {
        $entity->updateStatus(
            status: $application->status->value,
            externalReference: $application->externalReference,
            failureReason: $application->failureReason,
            updatedAt: $application->updatedAt,
            processingAt: $application->processingAt,
            completedAt: $application->completedAt,
        );
    }

}
