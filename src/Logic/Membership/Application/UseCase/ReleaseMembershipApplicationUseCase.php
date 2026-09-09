<?php

namespace App\Logic\Membership\Application\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Application\Dto\MembershipApplicationResponse;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\Model\MembershipType;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
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
use App\Logic\Membership\PaymentInterval;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use Psr\Log\LoggerInterface;

/**
 * Überführt einen abgeschlossenen Mitgliedsantrag in echte Mitglieder-Datensätze. Läuft
 * unabhängig vom Übertragungsstatus an das Fremdsystem (Claim/Complete/Fail/Retry), da beide
 * Vorgänge getrennt voneinander sind. Ein Antrag kann nur einmal freigegeben werden.
 *
 * Bei einer Familienmitgliedschaft wird die erste Person als Hauptmitglied geführt, weitere
 * Personen ab 21 Jahren als Partner, jüngere als Kind (eine im Antrag nicht erfasste Zuordnung,
 * die später im Freigabeprozess verfeinert werden kann).
 *
 * Verschickt zum Abschluss eine Bestätigungsmail (Mailvorlage
 * `MailTemplateKey::MembershipApplicationApproved`) an die E-Mail-Adresse der ersten Person — die
 * einzige, für die eine Adresse zwingend vorliegt (siehe `MembershipApplication`). Das Zusammenstellen
 * dieser Mail (Haushalt neu berechnen, Zusammenfassung bauen, Versand) läuft komplett „best effort“
 * in einem eigenen Try/Catch: Die Mitglieder wurden zu diesem Zeitpunkt bereits angelegt und der
 * Antrag bereits als freigegeben gespeichert — ein Fehler danach (z. B. beim Neuberechnen des
 * Beitrags oder beim Versand) darf die Freigabe selbst nicht mehr rückgängig machen oder als
 * Fehlschlag der Aktion erscheinen lassen.
 */
readonly class ReleaseMembershipApplicationUseCase
{
    private const string ASSOCIATION_NAME = 'Naturbad Borkheide e.V.';

    public function __construct(
        private MembershipApplicationManagerInterface $applications,
        private MemberOnboardingOrchestrator $orchestrator,
        private ClockInterface $clock,
        private NotificationMailer $notificationMailer,
        private HouseholdContributionRecalculator $householdRecalculator,
        private MemberManagerInterface $memberManager,
        private ContributionRateManagerInterface $contributionRates,
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
    private function sendApprovalConfirmation(?string $applicantEmail, array $members, \DateTimeImmutable $now): void
    {
        if ($applicantEmail === null || $members === []) {
            // $applicantEmail ist laut MembershipApplication für die erste Person zwingend gesetzt
            // — beide Fälle sind hier nur defensiv, nicht praktisch erreichbar.
            return;
        }

        try {
            // Der Familienrabatt hängt vom gesamten Haushalt ab (siehe MemberContributionCalculator);
            // bei der sequenziellen Anlage oben wurde er für zuerst angelegte Personen ggf. noch
            // ohne die später angelegten Geschwister berechnet. Vor der Beitragszusammenfassung
            // deshalb einmal den ganzen, jetzt vollständigen Haushalt neu berechnen und die
            // aktualisierten Datensätze (in der ursprünglichen Reihenfolge) für die E-Mail laden.
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
                    'beitrittsdatum' => $now->format('d.m.Y'),
                    'personen' => $this->formatMembers($members),
                    'beitraege' => $this->formatContributionsAsText($members),
                    'vereinsname' => self::ASSOCIATION_NAME,
                ],
                ['beitraege' => $this->formatContributionsAsHtml($members)],
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
     * @param list<Member> $members
     */
    private function formatMembers(array $members): string
    {
        return implode("\n", array_map(
            static fn (Member $member): string => sprintf(
                '- %s %s (%s), geb. %s',
                $member->firstName,
                $member->lastName,
                match ($member->familyRole) {
                    FamilyRole::None => 'Einzelperson',
                    FamilyRole::Head => 'Hauptmitglied',
                    FamilyRole::Partner => 'Familienangehöriger',
                    FamilyRole::Child => 'Kind',
                },
                $member->birthDate->format('d.m.Y'),
            ),
            $members,
        ));
    }

    /**
     * Text-Fallback der Beitragsübersicht: je Person eine Zeile mit den Gesamtkosten, darunter
     * eingerückt die einzelnen Positionen (Beitragssatz, ggf. Arbeitseinsatz-Zuschlag) — siehe
     * `contributionPositions()`.
     *
     * @param list<Member> $members
     */
    private function formatContributionsAsText(array $members): string
    {
        $lines = [];
        $totalCents = 0;
        foreach ($members as $member) {
            $positions = $this->contributionPositions($member);
            $memberTotalCents = array_sum(array_column($positions, 'amountCents'));
            $totalCents += $memberTotalCents;

            $lines[] = sprintf('- %s %s: %s pro Jahr', $member->firstName, $member->lastName, $this->formatEuro($memberTotalCents));
            foreach ($positions as $position) {
                $lines[] = sprintf('  - %s: %s pro Jahr', $position['label'], $this->formatEuro($position['amountCents']));
            }
        }
        $lines[] = sprintf('Gesamt: %s pro Jahr', $this->formatEuro($totalCents));

        return implode("\n", $lines);
    }

    /**
     * Dieselbe Beitragsübersicht als verschachtelte HTML-Liste (Person → Positionen), siehe
     * `MailContentRenderer` (verarbeitet `rawBlocks`) und `formatContributionsAsText()` für den
     * Text-Fallback derselben Daten.
     *
     * @param list<Member> $members
     */
    private function formatContributionsAsHtml(array $members): string
    {
        $totalCents = 0;
        $items = '';
        foreach ($members as $member) {
            $positions = $this->contributionPositions($member);
            $memberTotalCents = array_sum(array_column($positions, 'amountCents'));
            $totalCents += $memberTotalCents;

            $subItems = implode('', array_map(
                fn (array $position): string => sprintf(
                    '<li>%s: %s pro Jahr</li>',
                    $this->escapeHtml($position['label']),
                    $this->formatEuro($position['amountCents']),
                ),
                $positions,
            ));
            $items .= sprintf(
                '<li style="margin-bottom:8px;">%s %s: %s pro Jahr<ul style="margin:4px 0 0;padding-left:20px;">%s</ul></li>',
                $this->escapeHtml($member->firstName),
                $this->escapeHtml($member->lastName),
                $this->formatEuro($memberTotalCents),
                $subItems,
            );
        }

        return sprintf('<ul style="margin:0 0 12px;padding-left:20px;">%s</ul>', $items)
            .sprintf('<p style="margin:0;font-weight:bold;">Gesamt: %s pro Jahr</p>', $this->formatEuro($totalCents));
    }

    /**
     * Die einzelnen Positionen, aus denen sich der Jahresbeitrag einer Person zusammensetzt: der
     * eigentliche Beitragssatz (dessen admin-editierbare Bezeichnung, z. B. „Familienbeitrag
     * Erwachsene“) sowie — nur wenn die Person laut Berechnung dafür in Frage kommt (siehe
     * `MemberContributionCalculator`) — der Arbeitseinsatz-Zuschlag als eigene Position.
     *
     * @return list<array{label: string, amountCents: int}>
     */
    private function contributionPositions(Member $member): array
    {
        $rateLabel = $member->contributionCategory !== null
            ? $this->contributionRates->findByCategory($member->contributionCategory)?->label
            : null;
        $positions = [
            ['label' => $rateLabel ?? 'Mitgliedsbeitrag', 'amountCents' => $member->contributionAmountCents ?? 0],
        ];

        if ($member->workAssignmentSurchargeCents !== null) {
            $surchargeLabel = $this->contributionRates->findByCategory(ContributionCategory::WorkAssignmentSurcharge)?->label;
            $positions[] = ['label' => $surchargeLabel ?? 'Arbeitseinsatz-Zuschlag', 'amountCents' => $member->workAssignmentSurchargeCents];
        }

        return $positions;
    }

    private function formatEuro(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
