<?php

namespace App\Logic\Membership\Cancellation\UseCase;

use App\Logic\Membership\MembershipAdminLinkBuilderInterface;
use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\Cancellation\Manager\MemberCancellationManagerInterface;
use App\Logic\Membership\Cancellation\Model\MemberCancellation;
use App\Logic\Membership\Cancellation\Model\MemberCancellationStatus;
use App\Logic\Membership\Cancellation\Service\CancellationExitDate;
use App\Logic\Membership\Cancellation\Service\CancellationHandover;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\MemberAccess\Dto\MemberAccessSessionResponse;
use App\Logic\Membership\MemberAccess\Dto\MemberSelfServiceResponse;
use App\Logic\Membership\MemberAccess\Exception\InvalidMemberAccessTokenException;
use App\Logic\Membership\MemberAccess\UseCase\ResolveMemberAccessSessionUseCase;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

/**
 * Nimmt eine Kündigung aus „Meine Mitgliedschaft“ entgegen — für eine, mehrere oder alle sichtbaren
 * Personen auf einmal. Die Mitglieder selbst bleiben unverändert, bis die Mitgliederverwaltung die
 * Kündigung bestätigt (siehe `ConfirmMemberCancellationUseCase`); bis dahin zeigt „Meine
 * Mitgliedschaft“ sie als eingegangen an. Das Austrittsdatum ergibt sich aus der Kündigungsfrist
 * (siehe `CancellationExitDate`). Je Person entsteht ein Eintrag; die Übergabe (siehe
 * `CancellationHandover`) wird bei den Personen vermerkt, die sie betrifft.
 */
readonly class SubmitMemberCancellationUseCase
{
    public function __construct(
        private ResolveMemberAccessSessionUseCase $resolveSession,
        private MemberManagerInterface $members,
        private MemberCancellationManagerInterface $cancellations,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private NotificationMailer $notificationMailer,
        private MembershipAdminLinkBuilderInterface $adminLinks,
    ) {
    }

    /**
     * @param list<string> $memberIds
     */
    public function execute(
        string $rawToken,
        string $password,
        array $memberIds,
        ?string $successorMemberId,
        ?string $contactRecipientMemberId,
    ): MemberAccessSessionResponse {
        $session = $this->resolveSession->execute($rawToken, $password);
        $group = array_map(
            fn (MemberSelfServiceResponse $member): Member => $this->members->get($member->id),
            $session->members,
        );
        $open = $this->cancellations->openByMemberId();
        $cancelled = [];
        foreach (array_values(array_unique($memberIds)) as $memberId) {
            $member = $this->find($group, $memberId) ?? throw new InvalidMemberAccessTokenException();
            if ($member->leftAt !== null) {
                throw new BusinessRuleViolationException(sprintf('Für %s ist bereits ein Austritt eingetragen.', $this->name($member)));
            }
            if (isset($open[$member->id])) {
                throw new BusinessRuleViolationException(sprintf('Für %s ist bereits eine Kündigung eingegangen.', $this->name($member)));
            }
            $cancelled[] = $member;
        }
        if ($cancelled === []) {
            throw new BusinessRuleViolationException('Bitte mindestens eine Person auswählen.');
        }

        $candidates = CancellationHandover::candidates($cancelled, $group, $open);
        $successor = null;
        if (array_any($cancelled, static fn (Member $member): bool => CancellationHandover::requiresSuccessor($member, $candidates))) {
            $successor = $this->find($candidates, $successorMemberId)
                ?? throw new BusinessRuleViolationException('Bitte auswählen, wer künftig die Beiträge zahlt bzw. Hauptmitglied wird.');
        }
        $contactRecipient = null;
        if (CancellationHandover::requiresContactRecipient($cancelled, $candidates)) {
            $contactRecipient = $this->find($candidates, $contactRecipientMemberId ?? $successor?->id)
                ?? throw new BusinessRuleViolationException('Bitte auswählen, wer die Kontaktdaten (E-Mail, Telefon) übernimmt.');
        }

        $requester = $this->requester($group, $cancelled, $session->email);
        $now = $this->clock->now();
        $exitDate = CancellationExitDate::for($now);
        foreach ($cancelled as $member) {
            $memberSuccessor = CancellationHandover::requiresSuccessor($member, $candidates) ? $successor : null;
            $memberContactRecipient = $member->hasContactInfo() ? $contactRecipient : null;
            $this->cancellations->save(new MemberCancellation(
                id: $this->identifierGenerator->generate(),
                memberId: $member->id,
                memberNumber: $member->memberNumber,
                memberName: $this->name($member),
                primaryMemberNumber: $member->primaryMemberNumber,
                requestedByEmail: $session->email,
                requestedByName: $requester === null ? null : $this->name($requester),
                requestedByMemberNumber: $requester?->memberNumber,
                submittedAt: $now,
                exitDate: $exitDate,
                successorMemberId: $memberSuccessor?->id,
                successorName: $memberSuccessor === null ? null : $this->name($memberSuccessor),
                contactRecipientMemberId: $memberContactRecipient?->id,
                contactRecipientName: $memberContactRecipient === null ? null : $this->name($memberContactRecipient),
                status: MemberCancellationStatus::Open,
                decidedAt: null,
            ));
        }

        $this->notificationMailer->notify(NotificationEvent::MemberCancellationSubmitted, MailTemplateKey::MemberCancellationSubmittedNotification, [
            'name' => $requester === null ? $session->email : sprintf('%s (%s)', $this->name($requester), $requester->memberNumber),
            'personen' => implode("\n", array_map(fn (Member $member): string => sprintf('- %s (%s)', $this->name($member), $member->memberNumber), $cancelled)),
            'austrittsdatum' => $exitDate->format('d.m.Y'),
            'uebergabe' => $this->handoverText($successor, $contactRecipient),
            'link' => $this->adminLinks->cancellations(),
        ]);

        return $this->resolveSession->execute($rawToken, $password);
    }

    /**
     * @param list<Member> $members
     */
    private function find(array $members, ?string $id): ?Member
    {
        foreach ($members as $member) {
            if ($member->id === $id) {
                return $member;
            }
        }

        return null;
    }

    /**
     * Die Person hinter der Zugangs-E-Mail-Adresse — bei einer gemeinsamen Adresse bevorzugt eine der
     * gekündigten Personen, sonst die erste der Gruppe (in der Regel der Zahler).
     *
     * @param list<Member> $group
     * @param list<Member> $cancelled
     */
    private function requester(array $group, array $cancelled, string $email): ?Member
    {
        $withEmail = array_values(array_filter(
            $group,
            static fn (Member $candidate): bool => mb_strtolower(trim($candidate->email ?? '')) === mb_strtolower(trim($email)),
        ));
        foreach ($withEmail as $candidate) {
            if (in_array($candidate, $cancelled, true)) {
                return $candidate;
            }
        }

        return $withEmail[0] ?? null;
    }

    private function name(Member $member): string
    {
        return trim($member->firstName.' '.$member->lastName);
    }

    private function handoverText(?Member $successor, ?Member $contactRecipient): string
    {
        $lines = [];
        if ($successor !== null) {
            $lines[] = sprintf('Beiträge bzw. Hauptmitgliedschaft übernimmt: %s', $this->name($successor));
        }
        if ($contactRecipient !== null) {
            $lines[] = sprintf('Kontaktdaten übernimmt: %s', $this->name($contactRecipient));
        }

        return $lines === [] ? 'Keine Übergabe nötig.' : implode("\n", $lines);
    }
}
