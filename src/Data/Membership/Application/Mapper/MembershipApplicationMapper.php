<?php

namespace App\Data\Membership\Application\Mapper;

use App\Data\Membership\Application\Entity\MembershipApplicantEntity;
use App\Data\Membership\Application\Entity\MembershipApplicationEntity;
use App\Logic\Membership\Application\Model\Applicant;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Membership\Application\Model\MembershipType;
use App\Logic\Membership\Member\Model\Salutation;

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
                    salutation: Salutation::from($applicant->getSalutation()),
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
            version: $entity->getVersion(),
            submittedAt: $entity->getSubmittedAt(),
            updatedAt: $entity->getUpdatedAt(),
            releasedAt: $entity->getReleasedAt(),
            releasedMemberIds: $this->decodeMemberIds($entity->getReleasedMemberIds()),
            rejectedAt: $entity->getRejectedAt(),
            rejectionReason: $entity->getRejectionReason(),
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
            submittedAt: $application->submittedAt,
            updatedAt: $application->updatedAt,
            releasedAt: $application->releasedAt,
            releasedMemberIds: $this->encodeMemberIds($application->releasedMemberIds),
            rejectedAt: $application->rejectedAt,
            rejectionReason: $application->rejectionReason,
        );
        foreach ($application->applicants as $applicant) {
            $entity->addApplicant(new MembershipApplicantEntity(
                id: $applicant->id,
                application: $entity,
                position: $applicant->position,
                salutation: $applicant->salutation->value,
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
        $entity->release(
            releasedAt: $application->releasedAt,
            releasedMemberIds: $this->encodeMemberIds($application->releasedMemberIds),
            updatedAt: $application->updatedAt,
        );
        $entity->reject(
            rejectedAt: $application->rejectedAt,
            rejectionReason: $application->rejectionReason,
            updatedAt: $application->updatedAt,
        );
    }

    /**
     * @return list<string>|null
     */
    private function decodeMemberIds(?string $releasedMemberIds): ?array
    {
        if ($releasedMemberIds === null) {
            return null;
        }
        /** @var list<string> $decoded */
        $decoded = json_decode($releasedMemberIds, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param list<string>|null $memberIds
     */
    private function encodeMemberIds(?array $memberIds): ?string
    {
        return $memberIds === null ? null : json_encode($memberIds, JSON_THROW_ON_ERROR);
    }
}
