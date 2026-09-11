<?php

namespace App\Logic\Event\HelpRequest\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Event\HelpRequest\Dto\EventHelpRequestBroadcastRecipientsResponse;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use App\Logic\Event\HelpRequest\Service\EventHelpRequestRecipientResolver;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\MemberProviderInterface;

/**
 * Ermittelt, bevor eine Rundmail tatsächlich verschickt wird, welche E-Mail-Adressen dabei
 * automatisch angeschrieben würden — die Verwaltung sieht das im Feld „An:" vor dem eigentlichen
 * Versand und kann dort einzelne Adressen entfernen oder weitere ergänzen (siehe
 * `openEventHelpBroadcastDialog` in `assets/app.js`); der tatsächliche Versand
 * (`SendEventHelpRequestBroadcastUseCase`) verwendet danach genau die im Dialog stehen gebliebene
 * Liste, nicht mehr diese Ermittlung.
 *
 * - `defaultEmails`: exakt das, was `EventHelpRequestRecipientResolver` pro berücksichtigter
 *   Anmeldung liefert (eigene Mitglieds-Adresse, sonst der Haushalt, plus eine ggf. abweichende
 *   Formular-Angabe) — die Vorbelegung des „An:"-Felds.
 * - `suggestedEmails`: eine größere Vorschlagsliste fürs Nachtragen (Pulldown) — enthält
 *   `defaultEmails` sowie zusätzlich alle Adressen aus dem ganzen Haushalt eines zugeordneten
 *   Mitglieds, auch wenn die automatische Ermittlung sie mangels Bedarf nicht verwendet hätte (z. B.
 *   weil das Mitglied selbst schon eine eigene Adresse hat).
 *
 * Ohne `$requestIds` (Button „Mail an alle Helfer") werden nur Anmeldungen im Status „Neu" oder
 * „Teilgenommen" berücksichtigt — wer nicht teilgenommen hat oder bereits als erledigt markiert ist,
 * soll die Rundmail an alle nicht automatisch bekommen. Mit `$requestIds` (✉-Icon je Person)
 * entfällt dieser Statusfilter bewusst: Eine dort gezielt ausgewählte Person soll unabhängig von
 * ihrem Status erreichbar bleiben.
 */
readonly class GetEventHelpRequestBroadcastRecipientsUseCase
{
    public function __construct(
        private EventHelpRequestManagerInterface $manager,
        private MemberProviderInterface $memberProvider,
        private MemberManagerInterface $memberManager,
        private EventHelpRequestRecipientResolver $recipientResolver,
    ) {
    }

    /**
     * @param ?list<string> $requestIds Wenn gesetzt: nur diese Anmeldungen (statt aller der
     *                                  Veranstaltung) berücksichtigen — siehe
     *                                  `SendEventHelpRequestBroadcastUseCase`.
     */
    public function execute(string $eventIdentifier, ?array $requestIds = null): EventHelpRequestBroadcastRecipientsResponse
    {
        $requests = array_values(array_filter(
            $this->manager->all(),
            static fn (EventHelpRequest $request): bool => $request->eventIdentifier === $eventIdentifier
                && ($requestIds === null
                    ? in_array($request->status, [EventHelpRequestStatus::New, EventHelpRequestStatus::Participated], true)
                    : in_array($request->id, $requestIds, true)),
        ));
        if ($requests === []) {
            throw new BusinessRuleViolationException($requestIds === null
                ? 'Für diese Veranstaltung liegen keine Helferanmeldungen mit Status „Neu" oder „Teilgenommen" vor.'
                : 'Die ausgewählte(n) Helferanmeldung(en) wurde(n) nicht gefunden.');
        }

        $default = [];
        $suggested = [];
        foreach ($requests as $request) {
            $member = $request->memberId !== null ? $this->memberProvider->find($request->memberId) : null;
            foreach ($this->recipientResolver->resolve($member, $request->email) as $email) {
                $default[mb_strtolower(trim($email))] = $email;
            }
            if ($request->email !== null) {
                $suggested[mb_strtolower(trim($request->email))] = $request->email;
            }
            if ($member !== null) {
                // Liefert stets auch das Mitglied selbst mit (dessen `primaryMemberNumber` erfüllt
                // die eigene Abfrage trivial), daher genügt dieser eine Aufruf für Mitglied + Haushalt.
                foreach ($this->memberManager->findByPrimaryMemberNumber($member->primaryMemberNumber) as $householdMember) {
                    if ($householdMember->email !== null) {
                        $suggested[mb_strtolower(trim($householdMember->email))] = $householdMember->email;
                    }
                }
            }
        }
        foreach ($default as $key => $email) {
            $suggested[$key] = $email;
        }

        return new EventHelpRequestBroadcastRecipientsResponse(
            defaultEmails: array_values($default),
            suggestedEmails: array_values($suggested),
        );
    }
}
