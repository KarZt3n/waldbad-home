<?php

namespace App\Logic\Membership\MemberMessage\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\MemberAccess\Exception\InvalidMemberAccessTokenException;
use App\Logic\Membership\MemberAccess\UseCase\ResolveMemberAccessSessionUseCase;
use App\Logic\Membership\MemberMessage\Dto\MemberMessageResponse;
use App\Logic\Membership\MemberMessage\Manager\MemberMessageManagerInterface;
use App\Logic\Membership\MemberMessage\Model\MemberMessage;
use App\Logic\Membership\MemberMessage\Model\MemberMessageStatus;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

/**
 * Verschickt eine über „Meine Mitgliedschaft" eingereichte Nachricht — der Token bestätigt nur den
 * Zugriff auf die E-Mail-Adresse, nicht automatisch auf jedes einzelne Mitglied dahinter: die
 * Nachricht muss ausdrücklich für einen der über diesen Token erreichbaren Mitgliedsdatensätze
 * gesendet werden, sonst gilt derselbe Fehler wie bei einem ungültigen Token (`Silent-Fail`-Prinzip:
 * kein Unterschied erkennbar, ob der Token oder nur die Mitglieds-Zuordnung ungültig war).
 */
readonly class SendMemberMessageUseCase
{
    public function __construct(
        private ResolveMemberAccessSessionUseCase $resolveSession,
        private MemberMessageManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private NotificationMailer $notificationMailer,
    ) {
    }

    public function execute(string $rawToken, string $password, string $memberId, string $message): MemberMessageResponse
    {
        $session = $this->resolveSession->execute($rawToken, $password);
        $member = null;
        foreach ($session->members as $candidate) {
            if ($candidate->id === $memberId) {
                $member = $candidate;
                break;
            }
        }
        if ($member === null) {
            throw new InvalidMemberAccessTokenException();
        }

        $now = $this->clock->now();
        $saved = $this->manager->save(new MemberMessage(
            id: $this->identifierGenerator->generate(),
            memberId: $member->id,
            memberNumber: $member->memberNumber,
            memberName: trim($member->firstName.' '.$member->lastName),
            message: trim($message),
            status: MemberMessageStatus::New,
            submittedAt: $now,
            updatedAt: $now,
        ));

        $this->notificationMailer->notify(NotificationEvent::MemberMessageSubmitted, MailTemplateKey::MemberMessageSubmittedNotification, [
            'vorname' => $member->firstName,
            'nachname' => $member->lastName,
            'mitgliedsnummer' => $member->memberNumber,
            'nachricht' => trim($message),
        ]);

        return MemberMessageResponse::fromMessage($saved);
    }
}
