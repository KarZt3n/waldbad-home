<?php

namespace App\Tests\UI;

use App\Data\Membership\ContributionRate\Entity\ContributionRateEntity;
use App\Data\Membership\Member\Entity\MemberNumberSequenceEntity;
use App\Logic\IdentityAccess\User\Dto\CreateUserRequest;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\UseCase\CreateUserUseCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Deckt die freie Rundmail an Helfer einer Veranstaltung ab: die Vorbelegung des „An:"-Felds
 * (`GetEventHelpRequestBroadcastRecipientsUseCase`, Button „Mail an alle Helfer"/✉-Icon je Person)
 * sowie den eigentlichen Versand an die dort ggf. angepasste, explizit mitgeschickte Liste
 * (`SendEventHelpRequestBroadcastUseCase`), siehe `openEventHelpBroadcastDialog` in `assets/app.js`.
 */
final class EventHelpRequestBroadcastWorkflowTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('Der EntityManager ist im Testcontainer nicht verfügbar.');
        }
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $entityManager->persist(new MemberNumberSequenceEntity(id: 1, nextValue: 1));
        $entityManager->flush();
        $this->seedContributionRates($entityManager);
    }

    private function seedContributionRates(EntityManagerInterface $entityManager): void
    {
        $entityManager->persist(new ContributionRateEntity(
            id: 'rate-1',
            category: 'individual_senior',
            label: 'Einzelperson über 21 Jahre',
            amountCents: 5000,
            period: 'yearly',
            personGroup: 'individual',
            minAge: 21,
            maxAge: null,
            pendingLabel: null,
            pendingAmountCents: null,
            pendingPeriod: null,
            pendingPersonGroup: null,
            pendingMinAge: null,
            pendingMaxAge: null,
            pendingValidFrom: null,
        ));
        $entityManager->flush();
    }

    /**
     * Deckt zugleich die Vorschlagsliste ab: Das verknüpfte Mitglied (Karsten) hat selbst eine
     * E-Mail-Adresse -> `defaultEmails` enthält nur diese; ein Haushaltsmitglied (Sally) mit eigener
     * Adresse taucht trotzdem zusätzlich in `suggestedEmails` auf, zum Nachtragen im Dialog.
     */
    public function testBroadcastRecipientsReturnsDefaultEmailAndHouseholdSuggestion(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember('Karsten', 'Kuck', 'karsten@example.test', '1988-11-11'), $headers);
        self::assertResponseStatusCodeSame(201);
        $karsten = $this->responseData();

        $sally = $this->validMember('Sally', 'Kuck', 'sally@example.test', '1990-06-15');
        $sally['primaryMemberNumber'] = $karsten['memberNumber'];
        $sally['familyRole'] = 'partner';
        $sally['payerType'] = 'other_member';
        $sally['payerMemberId'] = $karsten['id'];
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $sally, $headers);
        self::assertResponseStatusCodeSame(201);

        $scheduleId = $this->createEventAndSubmitHelper($headers, 'Karsten', 'Kuck', '1988-11-11');

        $this->client->request('GET', '/api/admin/v1/event-help-requests/broadcast-recipients?eventIdentifier='.urlencode($scheduleId), server: $headers);
        self::assertResponseIsSuccessful();
        $result = $this->responseData();
        self::assertSame(['karsten@example.test'], $result['defaultEmails']);
        $suggested = $result['suggestedEmails'];
        self::assertIsArray($suggested);
        sort($suggested);
        self::assertSame(['karsten@example.test', 'sally@example.test'], $suggested);
    }

    /**
     * „Mail an alle Helfer" (kein `requestIds`) soll niemanden erreichen, der nicht teilgenommen hat
     * (Status „not_participated") — das ✉-Icon je Person (`requestIds` gezielt gesetzt) erreicht die
     * Person trotzdem, weil dort bewusst eine bestimmte Person angeschrieben wird.
     */
    public function testBroadcastRecipientsForAllHelpersExcludesNotParticipatedHelpers(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember('Erika', 'Musterfrau', 'erika@example.test', '1990-06-15'), $headers);
        self::assertResponseStatusCodeSame(201);
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember('Frida', 'Beispiel', 'frida@example.test', '1985-03-20'), $headers);
        self::assertResponseStatusCodeSame(201);

        $scheduleId = $this->createEventAndSubmitHelper($headers, 'Erika', 'Musterfrau', '1990-06-15');
        $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', [
            'eventIdentifier' => $scheduleId, 'firstName' => 'Frida', 'lastName' => 'Beispiel',
            'birthDate' => '1985-03-20', 'message' => '', 'activityIds' => [], 'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(202);

        $this->client->request('GET', '/api/admin/v1/event-help-requests', server: $headers);
        self::assertResponseIsSuccessful();
        $items = $this->responseData()['items'];
        self::assertIsArray($items);
        $erikaId = null;
        foreach ($items as $item) {
            self::assertIsArray($item);
            if ($item['lastName'] === 'Musterfrau') {
                $erikaId = $item['id'];
            }
        }
        self::assertIsString($erikaId);

        // Erika hat nicht teilgenommen -> soll aus der "alle Helfer"-Rundmail rausfallen.
        $this->client->jsonRequest('POST', "/api/admin/v1/event-help-requests/{$erikaId}/participation", [
            'participated' => false,
        ], $headers);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/admin/v1/event-help-requests/broadcast-recipients?eventIdentifier='.urlencode($scheduleId), server: $headers);
        self::assertResponseIsSuccessful();
        self::assertSame(['frida@example.test'], $this->responseData()['defaultEmails']);

        // Gezielt über die Anmeldungs-ID ausgewählt erreicht sie Erika aber trotzdem.
        $this->client->request('GET', '/api/admin/v1/event-help-requests/broadcast-recipients?eventIdentifier='.urlencode($scheduleId).'&requestIds[]='.urlencode($erikaId), server: $headers);
        self::assertResponseIsSuccessful();
        self::assertSame(['erika@example.test'], $this->responseData()['defaultEmails']);
    }

    public function testBroadcastRecipientsCanBeScopedByRequestIdsAndRequiresEventIdentifier(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $scheduleId = $this->createEventAndSubmitHelper($headers);

        $this->client->request('GET', '/api/admin/v1/event-help-requests/broadcast-recipients', server: $headers);
        self::assertResponseStatusCodeSame(400);

        $this->client->request('GET', '/api/admin/v1/event-help-requests/broadcast-recipients?eventIdentifier=unbekannte-veranstaltung', server: $headers);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('GET', '/api/admin/v1/event-help-requests/broadcast-recipients?eventIdentifier='.urlencode($scheduleId).'&requestIds[]=nicht-vorhanden', server: $headers);
        self::assertResponseStatusCodeSame(422);
    }

    public function testNonEditorIsDeniedAccessToBroadcastRecipients(): void
    {
        $adminHeaders = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $scheduleId = $this->createEventAndSubmitHelper($adminHeaders);

        $editorHeaders = ['HTTP_X_CSRF_TOKEN' => $this->loginAsPageOnlyEditor()];
        $this->client->request('GET', '/api/admin/v1/event-help-requests/broadcast-recipients?eventIdentifier='.urlencode($scheduleId), server: $editorHeaders);
        self::assertResponseStatusCodeSame(403);
    }

    public function testBroadcastFailsWithoutConfiguredMailServer(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];

        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests/broadcast', [
            'subject' => 'Treffpunkt', 'body' => 'Wir treffen uns am Eingang.', 'recipients' => ['erika@example.test'],
        ], $headers);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Mailversand', $this->responseData()['error']['message']);
    }

    public function testBroadcastRequiresSubjectBodyAndAtLeastOneRecipient(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];

        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests/broadcast', [
            'subject' => '', 'body' => 'Text ohne Betreff', 'recipients' => ['erika@example.test'],
        ], $headers);
        self::assertResponseStatusCodeSame(400);

        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests/broadcast', [
            'subject' => 'Hallo', 'body' => 'Text', 'recipients' => [],
        ], $headers);
        self::assertResponseStatusCodeSame(400);
    }

    public function testBroadcastRejectsAnInvalidRecipientEmail(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $this->configureBrokenMailServer($headers);

        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests/broadcast', [
            'subject' => 'Hallo', 'body' => 'Text', 'recipients' => ['keine-email-adresse'],
        ], $headers);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('gültige E-Mail-Adresse', $this->responseData()['error']['message']);
    }

    public function testBroadcastSendsToExplicitlyGivenRecipientsAndCountsFailures(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        // Absichtlich nicht erreichbarer Mailserver (Vorbild: SettingsEmailManagementWorkflowTest) —
        // der eigentliche Versand schlägt fehl, ohne den restlichen Ablauf zu sprengen.
        $this->configureBrokenMailServer($headers);

        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests/broadcast', [
            'subject' => 'Treffpunkt', 'body' => 'Wir treffen uns am Eingang.',
            // Dieselbe Adresse zweimal (unterschiedliche Groß-/Kleinschreibung) wird auf einen
            // Empfänger dedupliziert — das „An:"-Feld im Dialog könnte sowas theoretisch liefern.
            'recipients' => ['erika@example.test', ' Erika@Example.test '],
        ], $headers);
        self::assertResponseIsSuccessful();
        $result = $this->responseData();
        self::assertSame(1, $result['recipientCount']);
        self::assertSame(0, $result['sentCount']);
        self::assertSame(1, $result['failedCount']);
        self::assertArrayNotHasKey('skippedWithoutEmailCount', $result);
    }

    public function testNonEditorIsDeniedAccessToBroadcast(): void
    {
        $editorHeaders = ['HTTP_X_CSRF_TOKEN' => $this->loginAsPageOnlyEditor()];
        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests/broadcast', [
            'subject' => 'Hallo', 'body' => 'Text', 'recipients' => ['erika@example.test'],
        ], $editorHeaders);
        self::assertResponseStatusCodeSame(403);
    }

    private function configureBrokenMailServer(array $headers): void
    {
        $this->client->jsonRequest('PUT', '/api/admin/v1/email-settings', [
            'provider' => 'custom', 'host' => '127.0.0.1', 'port' => 1,
            'username' => null, 'password' => null,
            'fromAddress' => 'verein@example.test', 'fromName' => 'Naturbad Borkheide e.V.',
        ], $headers);
        self::assertResponseIsSuccessful();
    }

    /**
     * Legt eine Veranstaltung und eine Helferanmeldung dazu an (Vor-/Nachname/Geburtsdatum frei
     * wählbar, damit sie sich bei Bedarf automatisch einem zuvor separat angelegten Mitglied
     * zuordnet) — Grundfall für die Empfänger-Ermittlung.
     */
    private function createEventAndSubmitHelper(
        array $headers,
        string $firstName = 'Erika',
        string $lastName = 'Musterfrau',
        string $birthDate = '1990-06-15',
    ): string {
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $this->client->jsonRequest('POST', '/api/admin/v1/events', [
            'kind' => 'work_assignment',
            'title' => 'Frühjahrsputz',
            'date' => $today,
            'time' => '09:00',
            'content' => '',
            'helpEnabled' => true,
            'helpButtonLabel' => 'Ich möchte helfen!',
            'visible' => true,
            'activities' => [],
            'callToActions' => [],
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $scheduleId = $this->responseData()['id'];
        self::assertIsString($scheduleId);

        $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', [
            'eventIdentifier' => $scheduleId, 'firstName' => $firstName, 'lastName' => $lastName,
            'birthDate' => $birthDate, 'message' => '', 'activityIds' => [], 'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(202);

        return $scheduleId;
    }

    /** @return array<string, mixed> */
    private function validMember(string $firstName, string $lastName, ?string $email, string $birthDate): array
    {
        return [
            'primaryMemberNumber' => null,
            'salutation' => 'ms',
            'lastName' => $lastName,
            'firstName' => $firstName,
            'birthDate' => $birthDate,
            'street' => 'Kirchanger 14',
            'postalCode' => '14822',
            'city' => 'Borkheide',
            'email' => $email,
            'phone' => null,
            'familyRole' => 'none',
            'joinedAt' => '2026-01-01',
            'leftAt' => null,
            'active' => true,
            'function' => 'member',
            'accountHolder' => $firstName.' '.$lastName,
            'iban' => 'DE89370400440532013000',
            'bankName' => 'Testbank',
            'mandateReference' => null,
            'paymentMethod' => 'sepa_direct_debit',
            'paymentInterval' => 'yearly',
            'paymentDay' => 'first',
            'payerType' => 'self_payer',
            'payerMemberId' => null,
            'nextBookingMonth' => null,
            'nextBookingYear' => null,
        ];
    }

    private function loginAsAdmin(): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: 'event-help-broadcast-admin@example.test',
            displayName: 'Events Admin',
            plainPassword: 'Ein-sicheres-Testpasswort-2026',
            roles: [Role::SuperAdmin],
            moduleAccess: [
                new ModuleAccess(CmsModule::EventHelpers, ModuleRole::Editor),
            ],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login', [
            'email' => 'event-help-broadcast-admin@example.test',
            'password' => 'Ein-sicheres-Testpasswort-2026',
        ]);
        self::assertResponseIsSuccessful();
        $login = $this->responseData();
        self::assertIsString($login['csrfToken']);

        return $login['csrfToken'];
    }

    private function loginAsPageOnlyEditor(): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: 'event-help-broadcast-editor@example.test',
            displayName: 'Seiten-Redakteur',
            plainPassword: 'Ein-sicheres-Testpasswort-2026',
            roles: [],
            moduleAccess: [
                new ModuleAccess(CmsModule::Pages, ModuleRole::Editor),
            ],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login', [
            'email' => 'event-help-broadcast-editor@example.test',
            'password' => 'Ein-sicheres-Testpasswort-2026',
        ]);
        self::assertResponseIsSuccessful();
        $login = $this->responseData();
        self::assertIsString($login['csrfToken']);

        return $login['csrfToken'];
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        $content = $this->client->getResponse()->getContent();
        if (!is_string($content)) {
            throw new \LogicException('Die Testantwort enthält keinen lesbaren Inhalt.');
        }
        if ($content === '') {
            return [];
        }
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \LogicException('Die Testantwort enthält kein JSON.');
        }

        $response = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                throw new \LogicException('Die Testantwort enthält einen ungültigen Schlüssel.');
            }
            $response[$key] = $value;
        }

        return $response;
    }
}
