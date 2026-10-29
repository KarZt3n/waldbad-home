<?php

namespace App\Logic\Membership\Application\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\Iban;
use App\Logic\Membership\Application\Dto\MembershipApplicationResponse;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\Model\Applicant;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Membership\Application\Model\ApplicationMemberMatch;
use App\Logic\Membership\Application\Service\ApplicationMemberMatcher;
use App\Logic\Membership\Application\Service\HouseholdMemberListFormatter;
use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Orchestrator\MemberOnboardingOrchestrator;
use App\Logic\Membership\Member\Service\HouseholdContributionRecalculator;
use App\Logic\Membership\MemberAccess\MemberAccessLinkBuilderInterface;
use App\Logic\Settings\Email\Model\AssociationName;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use Psr\Log\LoggerInterface;

/**
 * „Antrag in bestehende Mitgliedschaft migrieren“: Statt einen neuen Haushalt anzulegen (siehe
 * `ReleaseMembershipApplicationUseCase`), werden die Personen des Antrags, die schon Mitglied sind
 * (siehe `ApplicationMemberMatcher`), mit den Angaben aus dem Antrag aktualisiert und die übrigen
 * Personen dem Haushalt hinzugefügt.
 *
 * - Je Feld mit abweichendem Wert entscheidet die Redaktion im Abgleich, ob der Wert aus dem Antrag
 *   oder der bisherige gilt (`$keepExisting`); ohne Angabe gilt der Antrag.
 * - Leere Angaben im Antrag (Telefon, E-Mail, Bank) überschreiben nichts.
 * - Eine geänderte Bankverbindung geht an das zahlende Mitglied; bei neuer IBAN wird dessen bisheriges
 *   SEPA-Mandat entfernt, weil es zur alten Verbindung gehört (neues Mandat über „Mandat erzeugen“).
 * - Die E-Mail-Einwilligung wird nur gesetzt, nie durch ein fehlendes Häkchen entzogen.
 * - Neue Personen zahlen über das zahlende Mitglied des Haushalts; Familienrollen und Beiträge
 *   werden anschließend für den ganzen Haushalt neu ermittelt.
 * - Bisherige Haushaltsmitglieder, die im Antrag fehlen, bleiben Mitglied, sofern die Redaktion
 *   nicht ausdrücklich ihren Austritt zum heutigen Tag einträgt (`$leaveMemberIds`). Hauptmitglied
 *   und zahlende Mitglieder sind davon ausgenommen (siehe `ApplicationMemberMatch::removalBlocker()`).
 *
 * Statt der „Willkommen“-Mail mit neuer Mitgliedsnummer geht eine eigene Bestätigung
 * (`MailTemplateKey::MembershipApplicationMerged`) mit dem Haushalt nach der Übernahme an die erste
 * Person des Antrags — wie bei der Freigabe best effort, da die Übernahme bereits gespeichert ist.
 */
readonly class MergeMembershipApplicationUseCase
{
    /** Felder einer gefundenen Person, für die der bisherige Wert beibehalten werden kann. */
    public const array APPLICANT_FIELDS = ['salutation', 'firstName', 'lastName', 'street', 'postalCode', 'city', 'phone', 'email'];
    public const array BANK_FIELDS = ['accountHolder', 'iban', 'bankName'];

    public function __construct(
        private MembershipApplicationManagerInterface $applications,
        private ApplicationMemberMatcher $matcher,
        private MemberManagerInterface $members,
        private MemberOnboardingOrchestrator $orchestrator,
        private HouseholdContributionRecalculator $recalculator,
        private ClockInterface $clock,
        private NotificationMailer $notificationMailer,
        private MemberAccessLinkBuilderInterface $memberAccessLinkBuilder,
        private HouseholdMemberListFormatter $memberListFormatter,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<int, list<string>> $keepExisting je Personenposition die Felder, für die der bisherige Wert bleibt
     * @param list<string> $keepExistingBank Felder der Bankverbindung, für die der bisherige Wert bleibt
     * @param list<string> $leaveMemberIds im Antrag fehlende Haushaltsmitglieder, deren Austritt eingetragen wird
     */
    public function execute(string $id, array $keepExisting = [], array $keepExistingBank = [], array $leaveMemberIds = []): MembershipApplicationResponse
    {
        foreach ([...array_merge(...array_values($keepExisting)), ...$keepExistingBank] as $field) {
            if (!in_array($field, [...self::APPLICANT_FIELDS, ...self::BANK_FIELDS], true)) {
                throw new BusinessRuleViolationException(sprintf('Das Feld "%s" kann nicht beibehalten werden.', $field));
            }
        }
        $application = $this->applications->get($id);
        if ($application->releasedAt !== null || $application->rejectedAt !== null) {
            throw new BusinessRuleViolationException('Der Mitgliedsantrag wurde bereits bearbeitet.');
        }
        $now = $this->clock->now();
        $match = $this->matcher->match($application, $now)
            ?? throw new BusinessRuleViolationException('Zu diesem Antrag wurde keine bestehende Mitgliedschaft gefunden.');
        if ($match->hasConflict()) {
            throw new BusinessRuleViolationException(sprintf(
                'Personen dieses Antrags gehören zu verschiedenen Mitgliedschaften (%s). Bitte in der Mitgliederverwaltung von Hand zusammenführen.',
                implode(', ', [$match->primaryMemberNumber, ...$match->conflictingMemberNumbers]),
            ));
        }
        $leavingMembers = $this->leavingMembers($match, $leaveMemberIds);

        $memberIds = [];
        $newApplicants = [];
        foreach ($application->applicants as $applicant) {
            $member = $match->matchedMembers[$applicant->position] ?? null;
            if ($member === null) {
                $newApplicants[] = $applicant;
                continue;
            }
            $memberIds[] = $this->members->save($this->withApplicantData($member, $applicant, $application->emailConsent, $keepExisting[$applicant->position] ?? []))->id;
        }

        if ($match->bankAccountMember !== null) {
            $this->updateBankAccount($this->members->get($match->bankAccountMember->id), $application, $keepExistingBank);
        }

        $head = $this->members->findByMemberNumber($match->primaryMemberNumber)
            ?? throw new BusinessRuleViolationException(sprintf('Das Hauptmitglied %s wurde nicht gefunden.', $match->primaryMemberNumber));
        if ($newApplicants !== []) {
            if ($head->familyRole === FamilyRole::None) {
                $head = $this->members->save($head->withFamilyRole(FamilyRole::Head));
            }
            $payer = $head->payerType === PayerType::SelfPayer || $head->payerMemberId === null ? $head : $this->members->get($head->payerMemberId);
            foreach ($newApplicants as $applicant) {
                $memberIds[] = $this->addToHousehold($applicant, $head, $payer, $application->emailConsent, $now)->id;
            }
        }

        $leftAt = $now->setTime(0, 0);
        foreach ($leavingMembers as $member) {
            $this->members->save($this->members->get($member->id)->withLeftAt($leftAt));
        }

        $this->recalculator->recalculate($this->members->get($head->id));

        $merged = $this->applications->save($application->mergeInto($match->primaryMemberNumber, $memberIds, $now));
        $this->sendMergeConfirmation($application->applicants[0]->email, $match->primaryMemberNumber, $now);

        return MembershipApplicationResponse::fromApplication($merged);
    }

    /**
     * @param list<string> $leaveMemberIds
     * @return list<Member>
     */
    private function leavingMembers(ApplicationMemberMatch $match, array $leaveMemberIds): array
    {
        $leaving = [];
        foreach (array_values(array_unique($leaveMemberIds)) as $memberId) {
            $member = null;
            foreach ($match->missingMembers as $missing) {
                if ($missing->id === $memberId) {
                    $member = $missing;
                }
            }
            if ($member === null) {
                throw new BusinessRuleViolationException('Ein Austritt kann nur für bisherige Haushaltsmitglieder eingetragen werden, die im Antrag fehlen.');
            }
            $blocker = $match->removalBlocker($member);
            if ($blocker !== null) {
                throw new BusinessRuleViolationException(sprintf(
                    'Für %s %s kann kein Austritt eingetragen werden (%s).',
                    $member->firstName,
                    $member->lastName,
                    $blocker,
                ));
            }
            $leaving[] = $member;
        }

        return $leaving;
    }

    private function sendMergeConfirmation(?string $applicantEmail, string $primaryMemberNumber, \DateTimeImmutable $now): void
    {
        if ($applicantEmail === null) {
            return;
        }

        try {
            $household = array_values(array_filter(
                $this->members->findByPrimaryMemberNumber($primaryMemberNumber),
                static fn (Member $member): bool => $member->isActive($now),
            ));
            usort($household, static fn (Member $a, Member $b): int => [$a->memberNumber !== $primaryMemberNumber, $a->birthDate] <=> [$b->memberNumber !== $primaryMemberNumber, $b->birthDate]);
            $head = $household[0] ?? null;
            if ($head === null) {
                return;
            }
            $this->notificationMailer->sendTo(
                $applicantEmail,
                MailTemplateKey::MembershipApplicationMerged,
                [
                    'vorname' => $head->firstName,
                    'nachname' => $head->lastName,
                    'mitgliedsnummer' => $primaryMemberNumber,
                    'personen' => $this->memberListFormatter->format($household, (int) $now->format('Y')),
                    'link' => $this->memberAccessLinkBuilder->buildEntry(),
                    'vereinsname' => AssociationName::CURRENT,
                ],
            );
        } catch (\Throwable $exception) {
            // Die Übernahme ist zu diesem Zeitpunkt bereits gespeichert; ein Versandfehler darf sie
            // nicht als fehlgeschlagen erscheinen lassen.
            $this->logger->error('Bestätigungsmail für übernommenen Mitgliedsantrag konnte nicht versendet werden: {message}', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param list<string> $keep
     */
    private function withApplicantData(Member $member, Applicant $applicant, bool $emailConsent, array $keep): Member
    {
        $kept = static fn (string $field): bool => in_array($field, $keep, true);
        $updated = $member
            ->withSalutation($kept('salutation') ? $member->salutation : $applicant->salutation)
            ->withContactInfo(
                firstName: $kept('firstName') ? $member->firstName : $applicant->firstName,
                lastName: $kept('lastName') ? $member->lastName : $applicant->lastName,
                street: $kept('street') ? $member->street : trim($applicant->street.' '.$applicant->houseNumber),
                postalCode: $kept('postalCode') ? $member->postalCode : $applicant->postalCode,
                city: $kept('city') ? $member->city : $applicant->city,
                phone: $kept('phone') ? $member->phone : $applicant->phone ?? $member->phone,
                email: $kept('email') ? $member->email : $applicant->email ?? $member->email,
            );

        return $emailConsent && $updated->email !== null ? $updated->withEmailConsent(true) : $updated;
    }

    /**
     * @param list<string> $keep
     */
    private function updateBankAccount(Member $payer, MembershipApplication $application, array $keep): void
    {
        $accountHolder = in_array('accountHolder', $keep, true) && $payer->accountHolder !== null ? $payer->accountHolder : $application->accountHolder;
        $iban = in_array('iban', $keep, true) && $payer->iban !== null ? Iban::normalize($payer->iban) : Iban::normalize($application->iban);
        $bankName = in_array('bankName', $keep, true) ? $payer->bankName : $application->bankName ?? $payer->bankName;
        $unchanged = $payer->iban !== null
            && Iban::normalize($payer->iban) === $iban
            && $payer->accountHolder === $accountHolder
            && $payer->bankName === $bankName;
        if ($unchanged) {
            return;
        }
        $this->members->save($payer->withBankAccount($accountHolder, $iban, $bankName));
    }

    private function addToHousehold(Applicant $applicant, Member $head, Member $payer, bool $emailConsent, \DateTimeImmutable $now): Member
    {
        $contributionAge = (int) $now->format('Y') - (int) $applicant->birthDate->format('Y');

        return $this->orchestrator->createFromRequest(new CreateMemberRequest(
            memberNumber: null,
            primaryMemberNumber: $head->memberNumber,
            salutation: $applicant->salutation,
            lastName: $applicant->lastName,
            firstName: $applicant->firstName,
            birthDate: $applicant->birthDate,
            street: trim($applicant->street.' '.$applicant->houseNumber),
            postalCode: $applicant->postalCode,
            city: $applicant->city,
            email: $applicant->email,
            phone: $applicant->phone,
            familyRole: $contributionAge >= 21 ? FamilyRole::Partner : FamilyRole::Child,
            joinedAt: $now,
            leftAt: null,
            function: MemberFunction::Member,
            accountHolder: null,
            iban: null,
            bankName: null,
            mandateReference: null,
            paymentMethod: $payer->paymentMethod,
            paymentInterval: $payer->paymentInterval,
            paymentDay: $payer->paymentDay,
            payerType: PayerType::OtherMember,
            payerMemberId: $payer->id,
            payerMemberNumber: null,
            nextBookingMonth: null,
            nextBookingYear: null,
            emailConsent: $emailConsent && $applicant->email !== null,
        ));
    }
}
