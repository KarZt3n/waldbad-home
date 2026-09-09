<?php

namespace App\Logic\Membership\Application\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\EmailDeliverabilityCheckerInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\Application\Dto\MembershipApplicationResponse;
use App\Logic\Membership\Application\Dto\SubmitMembershipApplicationRequest;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\Model\Applicant;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Membership\Application\Model\MembershipType;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

readonly class SubmitMembershipApplicationUseCase
{
    public function __construct(
        private MembershipApplicationManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private NotificationMailer $notificationMailer,
        private EmailDeliverabilityCheckerInterface $emailDeliverabilityChecker,
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
                salutation: $applicant->salutation,
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
        $this->assertEmailsAreDeliverable($applicants);
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
            version: 0,
            submittedAt: $now,
            updatedAt: $now,
        );

        $saved = $this->manager->save($application);

        $firstApplicant = $applicants[0];
        $this->notificationMailer->notify(
            NotificationEvent::MembershipApplicationSubmitted,
            MailTemplateKey::MembershipApplicationSubmittedNotification,
            [
                'vorname' => $firstApplicant->firstName,
                'nachname' => $firstApplicant->lastName,
                'mitgliedschaftsart' => $request->membershipType === MembershipType::Family ? 'Familie' : 'Einzelperson',
            ],
        );

        return MembershipApplicationResponse::fromApplication($saved);
    }

    /**
     * Geht über den reinen Format-Check in `Applicant` hinaus (siehe
     * `EmailDeliverabilityCheckerInterface`): prüft zusätzlich, ob die Domain jeder angegebenen
     * Adresse überhaupt E-Mails annehmen kann. Jede Adresse wird dabei nur einmal geprüft, auch
     * wenn mehrere Personen dieselbe verwenden (z. B. weil weitere Familienmitglieder die Adresse
     * von Person 1 übernommen haben).
     *
     * @param list<Applicant> $applicants
     */
    private function assertEmailsAreDeliverable(array $applicants): void
    {
        $checked = [];
        foreach ($applicants as $applicant) {
            if ($applicant->email === null || isset($checked[$applicant->email])) {
                continue;
            }
            $checked[$applicant->email] = true;
            if (!$this->emailDeliverabilityChecker->isDeliverable($applicant->email)) {
                throw new BusinessRuleViolationException(sprintf(
                    'Die E-Mail-Adresse "%s" scheint nicht zustellbar zu sein. Bitte überprüfen.',
                    $applicant->email,
                ));
            }
        }
    }
}
