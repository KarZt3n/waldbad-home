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
 * Deckt die Zuordnung einer Helferanmeldung ("Ich möchte Helfen!") zu einem Mitgliedsdatensatz ab
 * (siehe `EventHelpRequestMemberMatcher`, `LinkEventHelpRequestMemberUseCase`) — Grundlage für die
 * spätere Erstattung der Arbeitszeit-Pauschale zum Jahresende.
 */
final class EventHelpRequestMemberLinkWorkflowTest extends WebTestCase
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

    /**
     * Vorbild: `MembershipManagementWorkflowTest::seedContributionRates()` — ohne hinterlegten
     * Beitragssatz lehnt die Mitgliedsanlage das Alter der Testperson ab.
     */
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

    public function testMatchingSubmissionIsLinkedAutomaticallyAndAmbiguousOneManually(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];

        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember('Erika', 'Musterfrau', 'erika@example.test', '1990-06-15'), $headers);
        self::assertResponseStatusCodeSame(201);
        $member = $this->responseData();
        $memberId = $member['id'];
        $memberNumber = $member['memberNumber'];
        self::assertIsString($memberId);
        self::assertIsString($memberNumber);

        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember('Frida', 'Beispiel', 'frida@example.test', '1985-03-20'), $headers);
        self::assertResponseStatusCodeSame(201);
        $otherMember = $this->responseData();
        $otherMemberId = $otherMember['id'];
        $otherMemberNumber = $otherMember['memberNumber'];
        self::assertIsString($otherMemberId);
        self::assertIsString($otherMemberNumber);

        $this->client->jsonRequest('POST', '/api/admin/v1/event-activities', [
            'name' => 'Rasen mähen', 'description' => '', 'active' => true,
        ], $headers);
        self::assertResponseStatusCodeSame(201);

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $this->client->jsonRequest('POST', '/api/admin/v1/events', [
            'kind' => 'work_assignment',
            'title' => 'Frühjahrsputz',
            'date' => $today,
            'time' => '09:00',
            'content' => '<p>Wir machen das Waldbad startklar.</p>',
            'helpEnabled' => true,
            'helpButtonLabel' => 'Ich möchte helfen!',
            'visible' => true,
            'activities' => [],
            'callToActions' => [],
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $scheduleId = $this->responseData()['id'];
        self::assertIsString($scheduleId);

        // Name (case-insensitiv/getrimmt) + E-Mail passen exakt zum angelegten Mitglied -> automatisch verknüpft.
        $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', [
            'eventIdentifier' => $scheduleId,
            'firstName' => '  ERIKA ',
            'lastName' => ' musterfrau ',
            'message' => '',
            'activityIds' => [],
            'privacyAccepted' => true,
            'isMember' => true,
            'email' => ' Erika@Example.test ',
            'birthDate' => '1990-06-15',
        ]);
        self::assertResponseStatusCodeSame(202);

        // Kein passendes Mitglied -> bleibt unverknüpft, wird danach manuell verknüpft.
        $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', [
            'eventIdentifier' => $scheduleId,
            'firstName' => 'Helfer',
            'lastName' => 'Ohnematch',
            'message' => '',
            'activityIds' => [],
            'privacyAccepted' => true,
            'isMember' => true,
        ]);
        self::assertResponseStatusCodeSame(202);

        // Tippfehler im Vornamen ("Eryka" statt "Erika") -> kein automatischer Treffer, obwohl das
        // Mitglied existiert.
        $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', [
            'eventIdentifier' => $scheduleId,
            'firstName' => 'Eryka',
            'lastName' => 'Musterfrau',
            'message' => '',
            'activityIds' => [],
            'privacyAccepted' => true,
            'isMember' => true,
        ]);
        self::assertResponseStatusCodeSame(202);

        $this->client->request('GET', '/api/admin/v1/event-help-requests', server: $headers);
        self::assertResponseIsSuccessful();
        $items = $this->responseData()['items'];
        self::assertIsArray($items);
        self::assertCount(3, $items);
        $byLastName = [];
        foreach ($items as $item) {
            self::assertIsArray($item);
            $lastName = $item['lastName'];
            self::assertIsString($lastName);
            $byLastName[$lastName] = $item;
        }
        self::assertSame($memberNumber, $byLastName['musterfrau']['memberNumber']);
        self::assertSame($memberId, $byLastName['musterfrau']['memberId']);
        self::assertNull($byLastName['Ohnematch']['memberNumber']);
        $unmatchedId = $byLastName['Ohnematch']['id'];
        self::assertIsString($unmatchedId);
        self::assertNull($byLastName['Musterfrau']['memberNumber']);
        $typoId = $byLastName['Musterfrau']['id'];
        self::assertIsString($typoId);

        $this->client->request('GET', '/api/admin/v1/event-help-requests/member-candidates?search=Musterfrau', server: $headers);
        self::assertResponseIsSuccessful();
        $candidates = $this->responseData()['items'];
        self::assertIsArray($candidates);
        self::assertCount(1, $candidates);
        self::assertIsArray($candidates[0]);
        self::assertSame($memberId, $candidates[0]['id']);
        self::assertSame('Kirchanger 14', $candidates[0]['street']);
        self::assertSame('1990-06-15', $candidates[0]['birthDate']);

        // Zunächst (versehentlich) mit dem falschen Mitglied verknüpft ...
        $this->client->jsonRequest('POST', "/api/admin/v1/event-help-requests/{$unmatchedId}/member", [
            'memberId' => $otherMemberId, 'firstName' => 'Helfer', 'lastName' => 'Ohnematch',
        ], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame($otherMemberNumber, $this->responseData()['memberNumber']);

        // ... und nachträglich auf das richtige Mitglied korrigiert (Bearbeiten einer bestehenden
        // Verknüpfung). Erika hat zu dieser Veranstaltung bereits die automatisch verknüpfte
        // "musterfrau"-Anmeldung -> beide werden zu einer zusammengeführt (siehe
        // `EventHelpRequestDuplicateMerger`); welche der beiden ID dabei überlebt, ist Definitionssache
        // der Zusammenführung und wird hier bewusst nicht angenommen.
        $this->client->jsonRequest('POST', "/api/admin/v1/event-help-requests/{$unmatchedId}/member", [
            'memberId' => $memberId, 'firstName' => 'Helfer', 'lastName' => 'Ohnematch',
        ], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame($memberNumber, $this->responseData()['memberNumber']);

        $this->client->request('GET', '/api/admin/v1/event-help-requests', server: $headers);
        self::assertResponseIsSuccessful();
        $responseItems = $this->responseData()['items'];
        self::assertIsArray($responseItems);
        $itemsAfterMerge = $this->itemsLinkedTo($responseItems, $memberId);
        self::assertCount(1, $itemsAfterMerge, 'Die beiden Anmeldungen desselben Mitglieds zur selben Veranstaltung müssen zu einer zusammengeführt worden sein.');
        $survivorId = $itemsAfterMerge[0]['id'];
        self::assertIsString($survivorId);

        // Verknüpfung der übrig gebliebenen (zusammengeführten) Anmeldung wieder lösen (Vor-/Nachname
        // müssen trotzdem mitgeschickt werden).
        $this->client->jsonRequest('POST', "/api/admin/v1/event-help-requests/{$survivorId}/member", [
            'memberId' => '', 'firstName' => 'Helfer', 'lastName' => 'Ohnematch',
        ], $headers);
        self::assertResponseIsSuccessful();
        self::assertNull($this->responseData()['memberNumber']);

        // Tippfehler-Fall: mit dem Mitglied verknüpfen und gleichzeitig Vor-/Nachname der Anmeldung
        // aus dem Mitgliedsdatensatz übernehmen.
        $this->client->jsonRequest('POST', "/api/admin/v1/event-help-requests/{$typoId}/member", [
            'memberId' => $memberId, 'firstName' => 'Erika', 'lastName' => 'Musterfrau',
        ], $headers);
        self::assertResponseIsSuccessful();
        $corrected = $this->responseData();
        self::assertSame($memberNumber, $corrected['memberNumber']);
        self::assertSame('Erika', $corrected['memberFirstName']);
        self::assertSame('Musterfrau', $corrected['memberLastName']);
        self::assertSame('Erika', $corrected['firstName']);
        self::assertSame('Musterfrau', $corrected['lastName']);

        $this->client->request('GET', '/api/admin/v1/event-help-requests', server: $headers);
        self::assertResponseIsSuccessful();
        $itemsAfterCorrection = $this->responseData()['items'];
        self::assertIsArray($itemsAfterCorrection);
        $correctedItem = null;
        foreach ($itemsAfterCorrection as $item) {
            self::assertIsArray($item);
            if ($item['id'] === $typoId) {
                $correctedItem = $item;
            }
        }
        self::assertNotNull($correctedItem);
        self::assertSame('Erika', $correctedItem['firstName']);
        self::assertSame('Musterfrau', $correctedItem['lastName']);
    }

    /**
     * Deckt das automatische Zusammenführen beim Absenden ab (siehe
     * `SubmitEventHelpRequestUseCase`): meldet sich dieselbe (verknüpfte) Person kein zweites Mal
     * mit einer weiteren Aktivität zu derselben Veranstaltung an, entsteht kein zweiter Datensatz —
     * die Aktivität wird stattdessen in die bestehende Anmeldung übernommen.
     */
    public function testMatchingSubmissionMergesIntoExistingHelpRequestInsteadOfCreatingADuplicate(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];

        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember('Erika', 'Musterfrau', 'erika@example.test', '1990-06-15'), $headers);
        self::assertResponseStatusCodeSame(201);
        $memberId = $this->responseData()['id'];
        self::assertIsString($memberId);

        $this->client->jsonRequest('POST', '/api/admin/v1/event-activities', [
            'name' => 'Rasen mähen', 'description' => '', 'active' => true,
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $activity1Id = $this->responseData()['id'];
        self::assertIsString($activity1Id);

        $this->client->jsonRequest('POST', '/api/admin/v1/event-activities', [
            'name' => 'Kuchen backen', 'description' => '', 'active' => true,
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $activity2Id = $this->responseData()['id'];
        self::assertIsString($activity2Id);

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $this->client->jsonRequest('POST', '/api/admin/v1/events', [
            'kind' => 'work_assignment',
            'title' => 'Sommerfest',
            'date' => $today,
            'time' => '09:00',
            'content' => '',
            'helpEnabled' => true,
            'helpButtonLabel' => 'Ich möchte helfen!',
            'visible' => true,
            'activities' => [
                ['activityId' => $activity1Id, 'requiredHelpers' => 3, 'time' => '09:00', 'meetTime' => '08:45', 'meetPlace' => 'Eingang', 'remark' => null],
                ['activityId' => $activity2Id, 'requiredHelpers' => 3, 'time' => '09:00', 'meetTime' => '08:45', 'meetPlace' => 'Eingang', 'remark' => null],
            ],
            'callToActions' => [],
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $scheduleId = $this->responseData()['id'];
        self::assertIsString($scheduleId);

        $submit = function (array $activityIds) use ($scheduleId): void {
            $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', [
                'eventIdentifier' => $scheduleId,
                'firstName' => 'Erika',
                'lastName' => 'Musterfrau',
                'message' => '',
                'activityIds' => $activityIds,
                'privacyAccepted' => true,
                'isMember' => true,
                'email' => 'erika@example.test',
                'birthDate' => '1990-06-15',
            ]);
        };
        $submit([$activity1Id]);
        self::assertResponseStatusCodeSame(202);
        $submit([$activity2Id]);
        self::assertResponseStatusCodeSame(202);

        $this->client->request('GET', '/api/admin/v1/event-help-requests', server: $headers);
        self::assertResponseIsSuccessful();
        $responseItems = $this->responseData()['items'];
        self::assertIsArray($responseItems);
        $items = $this->itemsLinkedTo($responseItems, $memberId);
        self::assertCount(1, $items, 'Zwei Anmeldungen derselben verknüpften Person zur selben Veranstaltung dürfen keine zwei Datensätze erzeugen.');
        $selectedActivities = $items[0]['selectedActivities'];
        self::assertIsArray($selectedActivities);
        $selectedActivityIds = [];
        foreach ($selectedActivities as $activity) {
            self::assertIsArray($activity);
            $selectedActivityIds[] = $activity['activityId'];
        }
        sort($selectedActivityIds);
        $expectedActivityIds = [$activity1Id, $activity2Id];
        sort($expectedActivityIds);
        self::assertSame($expectedActivityIds, $selectedActivityIds);
    }

    /**
     * Deckt das Zusammenführen beim manuellen Verknüpfen ab (siehe `LinkEventHelpRequestMemberUseCase`
     * / `EventHelpRequestDuplicateMerger`): zwei noch unverknüpfte Anmeldungen mit je eigener erfasster
     * Teilnahmezeit werden, sobald beide demselben Mitglied zugeordnet werden, zu einer Anmeldung mit
     * addierter Arbeitszeit zusammengeführt.
     */
    public function testLinkingToAMemberWithAnExistingHelpRequestMergesParticipationTimes(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];

        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember('Frida', 'Beispiel', 'frida@example.test', '1985-03-20'), $headers);
        self::assertResponseStatusCodeSame(201);
        $memberId = $this->responseData()['id'];
        self::assertIsString($memberId);

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $this->client->jsonRequest('POST', '/api/admin/v1/events', [
            'kind' => 'work_assignment',
            'title' => 'Herbstputz',
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

        // Zwei (noch unverknüpfte) Anmeldungen derselben Person, z. B. weil sie sich aus Versehen
        // zweimal angemeldet hat — jede mit eigener, bereits erfasster Teilnahmezeit.
        $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', [
            'eventIdentifier' => $scheduleId, 'firstName' => 'Frida', 'lastName' => 'Duplikat1',
            'message' => '', 'activityIds' => [], 'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(202);
        $firstId = $this->responseData()['id'];
        self::assertIsString($firstId);

        $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', [
            'eventIdentifier' => $scheduleId, 'firstName' => 'Frida', 'lastName' => 'Duplikat2',
            'message' => '', 'activityIds' => [], 'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(202);
        $secondId = $this->responseData()['id'];
        self::assertIsString($secondId);

        $this->client->jsonRequest('POST', "/api/admin/v1/event-help-requests/{$firstId}/participation", [
            'participated' => true, 'intervals' => [['fromTime' => '09:00', 'toTime' => '10:00']],
        ], $headers);
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('POST', "/api/admin/v1/event-help-requests/{$secondId}/participation", [
            'participated' => true, 'intervals' => [['fromTime' => '10:00', 'toTime' => '11:00']],
        ], $headers);
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('POST', "/api/admin/v1/event-help-requests/{$firstId}/member", [
            'memberId' => $memberId, 'firstName' => 'Frida', 'lastName' => 'Beispiel',
        ], $headers);
        self::assertResponseIsSuccessful();

        // Die zweite Anmeldung mit demselben Mitglied zur selben Veranstaltung zu verknüpfen führt
        // beide zusammen: Teilnahmezeiten werden addiert, nur eine Anmeldung bleibt bestehen.
        $this->client->jsonRequest('POST', "/api/admin/v1/event-help-requests/{$secondId}/member", [
            'memberId' => $memberId, 'firstName' => 'Frida', 'lastName' => 'Beispiel',
        ], $headers);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/admin/v1/event-help-requests', server: $headers);
        self::assertResponseIsSuccessful();
        $responseItems = $this->responseData()['items'];
        self::assertIsArray($responseItems);
        $items = $this->itemsLinkedTo($responseItems, $memberId);
        self::assertCount(1, $items, 'Beide Teilnahmezeiten müssen in einer einzigen zusammengeführten Anmeldung landen.');
        $merged = $items[0];
        self::assertSame('participated', $merged['status']);
        self::assertSame(120, $merged['participationMinutes']);
        $intervals = $merged['participationIntervals'];
        self::assertIsArray($intervals);
        self::assertCount(2, $intervals);
        $times = [];
        foreach ($intervals as $interval) {
            self::assertIsArray($interval);
            $fromTime = $interval['fromTime'];
            $toTime = $interval['toTime'];
            self::assertIsString($fromTime);
            self::assertIsString($toTime);
            $times[] = $fromTime.'-'.$toTime;
        }
        sort($times);
        self::assertSame(['09:00-10:00', '10:00-11:00'], $times);
    }

    /**
     * @param array<mixed> $items
     * @return list<array<mixed, mixed>>
     */
    private function itemsLinkedTo(array $items, string $memberId): array
    {
        $matches = [];
        foreach ($items as $item) {
            self::assertIsArray($item);
            if ($item['memberId'] === $memberId) {
                $matches[] = $item;
            }
        }

        return $matches;
    }

    /** @return array<string, mixed> */
    private function validMember(string $firstName, string $lastName, string $email, string $birthDate): array
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
            email: 'event-help-member-link-admin@example.test',
            displayName: 'Events Admin',
            plainPassword: 'Ein-sicheres-Testpasswort-2026',
            roles: [Role::SuperAdmin],
            moduleAccess: [
                new ModuleAccess(CmsModule::Events, ModuleRole::Editor),
                new ModuleAccess(CmsModule::Activities, ModuleRole::Editor),
                new ModuleAccess(CmsModule::EventHelpers, ModuleRole::Editor),
                new ModuleAccess(CmsModule::Members, ModuleRole::Editor),
            ],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login', [
            'email' => 'event-help-member-link-admin@example.test',
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
