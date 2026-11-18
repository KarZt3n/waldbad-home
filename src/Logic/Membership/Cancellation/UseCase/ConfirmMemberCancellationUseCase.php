<?php

namespace App\Logic\Membership\Cancellation\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Cancellation\Dto\MemberCancellationResponse;
use App\Logic\Membership\Cancellation\Manager\MemberCancellationManagerInterface;
use App\Logic\Membership\Cancellation\Model\MemberCancellation;
use App\Logic\Membership\Cancellation\Model\MemberCancellationStatus;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\MemberPayerTransferTransactionInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Service\HouseholdContributionRecalculator;
use App\Logic\Membership\Member\Service\PayerTransfer;
use App\Logic\Membership\MemberAccess\MemberAccessLinkBuilderInterface;
use App\Logic\Settings\Email\Model\AssociationName;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

/**
 * Bestätigt eine Kündigung als Ganzes — alle Mitglieder, die dieselbe Person gekündigt hat (siehe
 * Reiter „Kündigungen“). Je Mitglied: Austrittsdatum setzen, Zahler-Rolle bzw. Hauptnummer und
 * Kontaktdaten wie gewählt übergeben (siehe `PayerTransfer`, `Member::takingOverContactFrom()`) und
 * die Beiträge der Familie neu berechnen. Alles in einer Transaktion: entweder für alle oder für
 * keinen. Danach erhält der Haushalt eine Bestätigung mit allen Personen — an die Adresse, über die
 * gekündigt wurde, und an die der gekündigten Mitglieder.
 *
 * Die Übergabe erfolgt nur, wenn sie zum Zeitpunkt der Bestätigung noch nötig ist — hat die
 * Verwaltung die Familie inzwischen selbst umgestellt, bleibt es dabei.
 */
readonly class ConfirmMemberCancellationUseCase
{
    public function __construct(
        private MemberCancellationManagerInterface $cancellations,
        private MemberManagerInterface $members,
        private PayerTransfer $payerTransfer,
        private HouseholdContributionRecalculator $recalculator,
        private MemberPayerTransferTransactionInterface $transaction,
        private ClockInterface $clock,
        private NotificationMailer $notificationMailer,
        private MemberAccessLinkBuilderInterface $memberAccessLinks,
    ) {
    }

    /**
     * @param list<string> $ids
     *
     * @return list<MemberCancellationResponse>
     */
    public function execute(array $ids): array
    {
        $cancellations = self::load($this->cancellations, $ids);
        usort($cancellations, static fn (MemberCancellation $left, MemberCancellation $right): int => $left->submittedAt <=> $right->submittedAt);

        $warnings = [];
        $confirmed = [];
        $leavingMembers = [];
        $this->transaction->execute(function () use ($cancellations, &$warnings, &$confirmed, &$leavingMembers): void {
            foreach ($cancellations as $cancellation) {
                $member = $this->members->get($cancellation->memberId);
                if ($cancellation->successorMemberId !== null) {
                    $this->transferPayer($member, $cancellation->successorMemberId);
                }
                if ($cancellation->contactRecipientMemberId !== null && $member->hasContactInfo()) {
                    $recipient = $this->members->get($cancellation->contactRecipientMemberId);
                    $updated = $recipient->takingOverContactFrom($member);
                    if ($updated !== $recipient) {
                        $this->members->save($updated);
                    }
                }

                $leaving = $this->members->save($this->members->get($member->id)->withLeftAt($cancellation->exitDate));
                $leavingMembers[] = $leaving;
                try {
                    $this->recalculator->recalculate($leaving);
                } catch (BusinessRuleViolationException $exception) {
                    $warnings[$exception->getMessage()] = true;
                }
                $confirmed[] = $this->cancellations->save($cancellation->decide(MemberCancellationStatus::Confirmed, $this->clock->now()));
            }
        });

        $this->sendConfirmation($confirmed, $leavingMembers);

        $warning = $warnings === []
            ? null
            : 'Kündigung bestätigt, aber die Beiträge der Familie konnten nicht neu berechnet werden: '.implode(' ', array_keys($warnings));

        return array_map(static fn (MemberCancellation $cancellation): MemberCancellationResponse => MemberCancellationResponse::fromCancellation($cancellation, $warning), $confirmed);
    }

    /**
     * @param list<string> $ids
     *
     * @return non-empty-list<MemberCancellation>
     */
    public static function load(MemberCancellationManagerInterface $manager, array $ids): array
    {
        $cancellations = array_map($manager->get(...), array_values(array_unique($ids)));
        if ($cancellations === []) {
            throw new BusinessRuleViolationException('Es wurde keine Kündigung ausgewählt.');
        }
        foreach ($cancellations as $cancellation) {
            if (!$cancellation->isOpen()) {
                throw new BusinessRuleViolationException(sprintf('Die Kündigung von %s wurde bereits bearbeitet.', $cancellation->memberName));
            }
        }

        return $cancellations;
    }

    private function transferPayer(Member $member, string $successorId): void
    {
        $paysForOthers = $member->payerType === PayerType::SelfPayer && array_any(
            $this->members->findByPayerMemberId($member->id),
            static fn (Member $other): bool => $other->id !== $member->id,
        );
        $headOfOthers = $member->memberNumber === $member->primaryMemberNumber && array_any(
            $this->members->findByPrimaryMemberNumber($member->memberNumber),
            static fn (Member $other): bool => $other->id !== $member->id,
        );
        if (!$paysForOthers && !$headOfOthers) {
            return;
        }

        $this->payerTransfer->transfer(
            $member,
            $successorId,
            keepSuccessorPaymentDetails: false,
            transferPrimaryMembership: $headOfOthers,
            previousStays: true,
        );
    }

    /**
     * @param list<MemberCancellation> $confirmed
     * @param list<Member>             $leavingMembers
     */
    private function sendConfirmation(array $confirmed, array $leavingMembers): void
    {
        $recipients = [];
        foreach ([...array_map(static fn (MemberCancellation $cancellation): string => $cancellation->requestedByEmail, $confirmed), ...array_map(static fn (Member $member): ?string => $member->email, $leavingMembers)] as $email) {
            if ($email !== null && trim($email) !== '') {
                $recipients[mb_strtolower(trim($email))] = true;
            }
        }
        $first = $confirmed[0] ?? null;
        if ($first === null) {
            return;
        }
        $placeholders = [
            'name' => $first->requestedByName ?? $first->memberName,
            'personen' => implode("\n", array_map(
                static fn (MemberCancellation $cancellation): string => sprintf('- %s (%s) zum %s', $cancellation->memberName, $cancellation->memberNumber, $cancellation->exitDate->format('d.m.Y')),
                $confirmed,
            )),
            'link' => $this->memberAccessLinks->buildEntry(),
            'vereinsname' => AssociationName::CURRENT,
        ];
        foreach (array_keys($recipients) as $recipient) {
            $this->notificationMailer->sendTo($recipient, MailTemplateKey::MemberCancellationConfirmed, $placeholders);
        }
    }
}
