<?php

namespace App\UI\Event\HelpRequest\Http;

use App\Logic\Event\HelpRequest\Query\ListEventHelpRequestsQuery;
use App\Logic\Event\HelpRequest\Dto\ParticipationIntervalInput;
use App\Logic\Event\HelpRequest\UseCase\GetEventHelpRequestBroadcastRecipientsUseCase;
use App\Logic\Event\HelpRequest\UseCase\LinkEventHelpRequestMemberUseCase;
use App\Logic\Event\HelpRequest\UseCase\RecordEventHelpParticipationUseCase;
use App\Logic\Event\HelpRequest\UseCase\SendEventHelpRequestBroadcastUseCase;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/event-help-requests')]
class AdminEventHelpRequestController extends AbstractController
{
    private const int MEMBER_CANDIDATES_LIMIT = 20;

    public function __construct(private readonly EventHelpRequestResponseFactory $responseFactory)
    {
    }

    #[Route('', name: 'api_admin_event_help_list', methods: ['GET'])]
    public function list(ListEventHelpRequestsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventHelpersView->value);

        return new JsonResponse($this->responseFactory->collection($query->execute()));
    }

    /**
     * Eigener, schlanker Such-Endpunkt für den „Mitglied verknüpfen"-Dialog: liefert nur die für
     * die Suche/Anzeige nötigen Felder und benötigt bewusst nicht `Permission::MembersView`, damit
     * auch Helfer-Verwalter ohne volles Mitgliederverwaltung-Recht verknüpfen können.
     */
    #[Route('/member-candidates', name: 'api_admin_event_help_member_candidates', methods: ['GET'])]
    public function memberCandidates(Request $request, MemberManagerInterface $members): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventHelpersEdit->value);
        $search = trim((string) $request->query->get('search', ''));
        if ($search === '') {
            return new JsonResponse(['items' => []]);
        }

        $candidates = array_slice($members->search($search), 0, self::MEMBER_CANDIDATES_LIMIT);

        return new JsonResponse(['items' => array_map(static fn (Member $member): array => [
            'id' => $member->id,
            'firstName' => $member->firstName,
            'lastName' => $member->lastName,
            'memberNumber' => $member->memberNumber,
            // Nur zur Unterscheidung bei Namensgleichheit im Dialog benötigt (siehe `assets/app.js`,
            // `openLinkMemberDialog`).
            'street' => $member->street,
            'birthDate' => $member->birthDate->format('Y-m-d'),
        ], $candidates)]);
    }

    /**
     * Vorbelegung für das „An:"-Feld im Rundmail-Dialog, bevor tatsächlich etwas verschickt wird
     * (siehe `GetEventHelpRequestBroadcastRecipientsUseCase`) — die Verwaltung kann die gelieferte
     * Liste dort noch anpassen, bevor sie mit `broadcast()` unten tatsächlich verschickt wird.
     */
    #[Route('/broadcast-recipients', name: 'api_admin_event_help_broadcast_recipients', methods: ['GET'])]
    public function broadcastRecipients(Request $request, GetEventHelpRequestBroadcastRecipientsUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventHelpersEdit->value);
        $eventIdentifier = trim((string) $request->query->get('eventIdentifier', ''));
        if ($eventIdentifier === '') {
            throw new BadRequestHttpException('Veranstaltung ist erforderlich.');
        }

        $result = $useCase->execute($eventIdentifier, $this->parseRequestIds($request->query->all('requestIds')));

        return new JsonResponse([
            'defaultEmails' => $result->defaultEmails,
            'suggestedEmails' => $result->suggestedEmails,
        ]);
    }

    /**
     * Freie Rundmail (Betreff/Text von der Verwaltung selbst getippt) an die im Dialog „An:"
     * stehenden Adressen — siehe `SendEventHelpRequestBroadcastUseCase`. Ausgelöst entweder über den
     * Button „Mail an alle Helfer" je Veranstaltung oder über das ✉-Icon neben einer einzelnen
     * Person, beides in `assets/app.js` (`openEventHelpBroadcastDialog`, `buildMailButton`) — beide
     * holen die Vorbelegung zuvor über `broadcastRecipients()` und schicken hier bereits die
     * (ggf. angepasste) endgültige Liste mit.
     */
    #[Route('/broadcast', name: 'api_admin_event_help_broadcast', methods: ['POST'])]
    public function broadcast(Request $request, SendEventHelpRequestBroadcastUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventHelpersEdit->value);
        $data = $request->getPayload();
        $subject = trim($data->getString('subject'));
        $body = trim($data->getString('body'));
        if ($subject === '' || $body === '') {
            throw new BadRequestHttpException('Betreff und Text sind erforderlich.');
        }
        $recipients = $this->parseRequestIds($data->all('recipients'), 'Die Empfänger-E-Mail-Adressen sind ungültig.');
        if ($recipients === null || $recipients === []) {
            throw new BadRequestHttpException('Mindestens eine Empfänger-E-Mail-Adresse ist erforderlich.');
        }

        $result = $useCase->execute($subject, $body, $recipients);

        return new JsonResponse([
            'recipientCount' => $result->recipientCount,
            'sentCount' => $result->sentCount,
            'failedCount' => $result->failedCount,
        ]);
    }

    /**
     * @return ?list<string> `null`, wenn `$rawValues` leer ist (kein Filter/keine Angabe) — sonst
     *                        die getrimmten, nicht-leeren Werte.
     */
    private function parseRequestIds(array $rawValues, string $errorMessage = 'Die ausgewählten Helferanmeldungen sind ungültig.'): ?array
    {
        if ($rawValues === []) {
            return null;
        }
        if (!array_is_list($rawValues) || count($rawValues) > 50) {
            throw new BadRequestHttpException($errorMessage);
        }
        $values = [];
        foreach ($rawValues as $rawValue) {
            if (!is_string($rawValue) || trim($rawValue) === '') {
                throw new BadRequestHttpException($errorMessage);
            }
            $values[] = $rawValue;
        }

        return $values;
    }

    #[Route('/{id}/member', name: 'api_admin_event_help_link_member', methods: ['POST'])]
    public function linkMember(string $id, Request $request, LinkEventHelpRequestMemberUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventHelpersEdit->value);
        $data = $request->getPayload();
        $memberId = trim($data->getString('memberId'));
        $firstName = trim($data->getString('firstName'));
        $lastName = trim($data->getString('lastName'));
        if ($firstName === '' || $lastName === '') {
            throw new BadRequestHttpException('Vorname und Nachname sind erforderlich.');
        }

        return new JsonResponse($this->responseFactory->request($useCase->execute($id, $memberId === '' ? null : $memberId, $firstName, $lastName)));
    }

    #[Route('/{id}/participation', name: 'api_admin_event_help_participation', methods: ['POST'])]
    public function participation(string $id, Request $request, RecordEventHelpParticipationUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventHelpersEdit->value);
        $data = $request->getPayload();
        $participated = $data->getBoolean('participated');
        $intervals = [];
        if ($participated) {
            $rawIntervals = $data->all('intervals');
            if (!array_is_list($rawIntervals) || $rawIntervals === [] || count($rawIntervals) > 10) {
                throw new BadRequestHttpException('Es müssen zwischen einem und zehn Hilfezeiträume angegeben werden.');
            }
            foreach ($rawIntervals as $rawInterval) {
                if (!is_array($rawInterval)
                    || !is_string($rawInterval['fromTime'] ?? null)
                    || !is_string($rawInterval['toTime'] ?? null)) {
                    throw new BadRequestHttpException('Jeder Hilfezeitraum benötigt eine Von- und Bis-Uhrzeit.');
                }
                $intervals[] = new ParticipationIntervalInput(
                    fromTime: trim($rawInterval['fromTime']),
                    toTime: trim($rawInterval['toTime']),
                );
            }
        }

        return new JsonResponse($this->responseFactory->request($useCase->execute($id, $participated, $intervals)));
    }
}
