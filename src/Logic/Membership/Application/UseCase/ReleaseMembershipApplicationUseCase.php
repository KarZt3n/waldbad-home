<?php

namespace App\Logic\Membership\Application\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Application\Dto\MembershipApplicationResponse;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\Model\MembershipType;
use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Orchestrator\MemberOnboardingOrchestrator;
use App\Logic\Membership\Member\Service\HouseholdContributionRecalculator;
use App\Logic\Membership\MemberAccess\MemberAccessLinkBuilderInterface;
use App\Logic\Membership\PaymentInterval;
use App\Logic\Settings\Email\Model\AssociationName;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use Psr\Log\LoggerInterface;

/**
 * Überführt einen abgeschlossenen Mitgliedsantrag in echte Mitglieder-Datensätze. Läuft
 * unabhängig vom Übertragungsstatus an das Fremdsystem (Claim/Complete/Fail/Retry), da beide
 * Vorgänge getrennt voneinander sind. Ein Antrag kann nur einmal freigegeben werden.
 *
 * Bei einer Familienmitgliedschaft wird die erste Person als Hauptmitglied geführt, weitere
 * Personen mit einem Beitragsalter (Stichtag 31.12., siehe `Member::contributionAge()`) ab 21
 * Jahren als Partner, jüngere als Kind (eine im Antrag nicht erfasste Zuordnung, die später im
 * Freigabeprozess verfeinert werden kann) — dieselbe Altersdefinition wie bei der anschließenden
 * Beitragsberechnung, damit ein Kind, das im laufenden Jahr noch 21 wird, nicht erst als Kind
 * angelegt und direkt danach zur Einzelperson umgestuft wird.
 *
 * Eintrittsdatum ist der Tag der Annahme (Freigabe), nicht der Eingang des Antrags. Beitrag
 * und nächste Abbuchung (März des Folgejahres) ergeben sich daraus in `MemberOnboardingOrchestrator`.
 *
 * Verschickt zum Abschluss eine Bestätigungsmail (Mailvorlage
 * `MailTemplateKey::MembershipApplicationApproved`) an die E-Mail-Adresse der ersten Person — die
 * einzige, für die eine Adresse zwingend vorliegt (siehe `MembershipApplication`). Beiträge nennt die
 * Mail bewusst nicht, sondern verweist dafür auf „Meine Mitgliedschaft“. Das Zusammenstellen
 * dieser Mail (Haushalt neu berechnen, Personenliste bauen, Versand) läuft komplett „best effort“
 * in einem eigenen Try/Catch: Die Mitglieder wurden zu diesem Zeitpunkt bereits angelegt und der
 * Antrag bereits als freigegeben gespeichert — ein Fehler danach (z. B. beim Neuberechnen des
 * Beitrags oder beim Versand) darf die Freigabe selbst nicht mehr rückgängig machen oder als
 * Fehlschlag der Aktion erscheinen lassen.
 */
readonly class ReleaseMembershipApplicationUseCase
{
    public function __construct(
        private MembershipApplicationManagerInterface $applications,
        private MemberOnboardingOrchestrator $orchestrator,
        private ClockInterface $clock,
        private NotificationMailer $notificationMailer,
        private HouseholdContributionRecalculator $householdRecalculator,
        private MemberManagerInterface $memberManager,
        private MemberAccessLinkBuilderInterface $memberAccessLinkBuilder,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(string $id): MembershipApplicationResponse
    {
        $application = $this->applications->get($id);
        if ($application->releasedAt !== null) {
            throw new BusinessRuleViolationException('Der Mitgliedsantrag wurde bereits als Mitglied angelegt.');
        }
        $now = $this->clock->now();

        /** @var list<Member> $members */
        $members = [];
        $headMemberNumber = null;
        $headMemberId = null;
        foreach ($application->applicants as $index => $applicant) {
            $isHead = $index === 0;
            $isFamily = $application->membershipType === MembershipType::Family;
            $contributionAge = (int) $now->format('Y') - (int) $applicant->birthDate->format('Y');
            $familyRole = match (true) {
                !$isFamily => FamilyRole::None,
                $isHead => FamilyRole::Head,
                $contributionAge >= 21 => FamilyRole::Partner,
                default => FamilyRole::Child,
            };

            $member = $this->orchestrator->createFromRequest(new CreateMemberRequest(
                memberNumber: null,
                primaryMemberNumber: $isHead ? null : $headMemberNumber,
                salutation: $applicant->salutation,
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
                emailConsent: $application->emailConsent,
            ));

            $members[] = $member;
            if ($isHead) {
                $headMemberNumber = $member->memberNumber;
                $headMemberId = $member->id;
            }
        }

        $released = $this->applications->save($application->release(
            array_map(static fn (Member $member): string => $member->id, $members),
            $now,
        ));

        $this->sendApprovalConfirmation($application->applicants[0]->email, $members, $now);

        return MembershipApplicationResponse::fromApplication($released);
    }

    /**
     * @param list<Member> $members
     */
    private function sendApprovalConfirmation(?string $applicantEmail, array $members, \DateTimeImmutable $joinedAt): void
    {
        if ($applicantEmail === null || $members === []) {
            // $applicantEmail ist laut MembershipApplication für die erste Person zwingend gesetzt
            // — beide Fälle sind hier nur defensiv, nicht praktisch erreichbar.
            return;
        }

        try {
            // Der Familienrabatt hängt vom gesamten Haushalt ab (siehe MemberContributionCalculator);
            // bei der sequenziellen Anlage oben wurde er für zuerst angelegte Personen ggf. noch
            // ohne die später angelegten Geschwister berechnet. Deshalb einmal den ganzen, jetzt
            // vollständigen Haushalt neu berechnen und die aktualisierten Datensätze (ggf. geänderte
            // Familienrollen, in der ursprünglichen Reihenfolge) für die Personenliste laden.
            $this->householdRecalculator->recalculate($members[0]);
            $refreshedById = [];
            foreach ($this->memberManager->findByPrimaryMemberNumber($members[0]->primaryMemberNumber) as $refreshed) {
                $refreshedById[$refreshed->id] = $refreshed;
            }
            $refreshedMembers = array_values(array_filter(array_map(
                static fn (Member $member): ?Member => $refreshedById[$member->id] ?? null,
                $members,
            )));
            if ($refreshedMembers !== []) {
                $members = $refreshedMembers;
            }

            $head = $members[0];
            $this->notificationMailer->sendTo(
                $applicantEmail,
                MailTemplateKey::MembershipApplicationApproved,
                [
                    'vorname' => $head->firstName,
                    'nachname' => $head->lastName,
                    'mitgliedsnummer' => $head->memberNumber,
                    'beitrittsdatum' => $joinedAt->format('d.m.Y'),
                    'personen' => $this->formatMembers($members, (int) $joinedAt->format('Y')),
                    'link' => $this->memberAccessLinkBuilder->buildEntry(),
                    'vereinsname' => AssociationName::CURRENT,
                ],
            );
        } catch (\Throwable $exception) {
            // Die Mitglieder sind zu diesem Zeitpunkt bereits angelegt und der Antrag bereits als
            // freigegeben gespeichert — ein Fehler hier (z. B. fehlender Beitragssatz bei der
            // Neuberechnung) darf die Freigabe nicht als fehlgeschlagen erscheinen lassen.
            $this->logger->error('Bestätigungsmail für freigegebenen Mitgliedsantrag konnte nicht vorbereitet/versendet werden: {message}', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Je Person zusätzlich das Beitragsalter (Stichtag 31.12., siehe `Member::contributionAge()`),
     * damit nachvollziehbar ist, warum z. B. ein erst im Dezember 21-Jähriger schon den
     * Erwachsenenbeitrag zahlt.
     *
     * @param list<Member> $members
     */
    private function formatMembers(array $members, int $contributionYear): string
    {
        return implode("\n", array_map(
            static fn (Member $member): string => sprintf(
                '- %s %s (%s), geb. %s (Beitragsalter %d: %d)',
                $member->firstName,
                $member->lastName,
                match ($member->familyRole) {
                    FamilyRole::None => 'Einzelperson',
                    FamilyRole::Head => 'Hauptmitglied',
                    FamilyRole::Partner => 'Familienangehöriger',
                    FamilyRole::Child => 'Kind',
                },
                $member->birthDate->format('d.m.Y'),
                $contributionYear,
                $member->contributionAge($contributionYear),
            ),
            $members,
        ));
    }
}
