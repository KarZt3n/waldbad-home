<?php

namespace App\Logic\Event\HelpRequest\Service;

use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;

/**
 * Ermittelt die E-Mail-Adresse(n), an die eine Helferanmeldung erreichbar ist — gemeinsame Grundlage
 * für `EventHelpRequestConfirmationMailer` (automatische Bestätigung einer einzelnen Anmeldung) und
 * `SendEventHelpRequestBroadcastUseCase` (freie Rundmail an alle Helfer einer Veranstaltung):
 *
 * - Hat das zugeordnete Mitglied eine eigene E-Mail-Adresse, wird nur diese verwendet.
 * - Sonst der ganze Haushalt (alle Mitglieder mit derselben `primaryMemberNumber`, die eine
 *   E-Mail-Adresse hinterlegt haben) — z. B. wenn das zugeordnete Mitglied selbst keine eigene
 *   Adresse hinterlegt hat.
 * - Eine im Formular freiwillig angegebene E-Mail-Adresse kommt zusätzlich dazu, wenn sie davon
 *   abweicht — z. B. weil sich ein Familienmitglied ohne eigene hinterlegte Adresse mit der
 *   E-Mail-Adresse des Hauptmitglieds angemeldet hat — bzw. als einziger Treffer, wenn gar kein
 *   Mitglied zugeordnet werden konnte.
 */
readonly class EventHelpRequestRecipientResolver
{
    public function __construct(private MemberManagerInterface $members)
    {
    }

    /**
     * @return list<string>
     */
    public function resolve(?Member $member, ?string $submittedEmail): array
    {
        $recipients = [];
        if ($member !== null) {
            if ($member->email !== null) {
                $recipients[] = $member->email;
            } else {
                foreach ($this->members->findByPrimaryMemberNumber($member->primaryMemberNumber) as $householdMember) {
                    if ($householdMember->email !== null) {
                        $recipients[] = $householdMember->email;
                    }
                }
            }
        }
        if ($submittedEmail !== null) {
            $normalizedSubmittedEmail = mb_strtolower(trim($submittedEmail));
            $alreadyIncluded = array_filter(
                $recipients,
                static fn (string $recipient): bool => mb_strtolower(trim($recipient)) === $normalizedSubmittedEmail,
            );
            if ($alreadyIncluded === []) {
                $recipients[] = $submittedEmail;
            }
        }

        // Case-insensitiv dedupen (z. B. wenn zwei Haushaltsmitglieder dieselbe Adresse hinterlegt haben).
        $unique = [];
        foreach ($recipients as $recipient) {
            $unique[mb_strtolower(trim($recipient))] = $recipient;
        }

        return array_values($unique);
    }
}
