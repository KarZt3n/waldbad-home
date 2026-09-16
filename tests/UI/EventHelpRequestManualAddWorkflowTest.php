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
use App\Tests\Support\FixedSecureTokenGenerator;

/**
 * Deckt das manuelle Hinzufügen eines Mitglieds als Helfer einer Veranstaltung ab (Button „+" neben
 * „Mail an alle Helfer" in der Helferverwaltung, siehe `AddEventHelpRequestUseCase` und
 * `openAddEventHelperDialog` in `assets/admin/events.js`) — für Personen, die sich nicht über das öffentliche
 * Formular angemeldet haben.
 */
final class EventHelpRequestManualAddWorkflowTest extends WebTestCase
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

    public function testAdminCanManuallyAddAMemberAsHelperButNotTwice(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];

        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember('Erika', 'Musterfrau', 'erika@example.test', '1990-06-15'), $headers);
        self::assertResponseStatusCodeSame(201);
        $member = $this->responseData();
        $memberId = $member['id'];
        $memberNumber = $member['memberNumber'];
        self::assertIsString($memberId);
        self::assertIsString($memberNumber);

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

        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests', [
            'eventIdentifier' => $scheduleId,
            'memberId' => $memberId,
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $created = $this->responseData();
        self::assertSame($memberId, $created['memberId']);
        self::assertSame($memberNumber, $created['memberNumber']);
        self::assertSame('Erika', $created['firstName']);
        self::assertSame('Musterfrau', $created['lastName']);
        self::assertSame('new', $created['status']);
        self::assertSame(['erika@example.test'], $created['recipientEmails']);

        $this->client->request('GET', '/api/admin/v1/event-help-requests', server: $headers);
        self::assertResponseIsSuccessful();
        $items = $this->responseData()['items'];
        self::assertIsArray($items);
        self::assertCount(1, $items);

        // Für dasselbe Mitglied und dieselbe Veranstaltung darf kein zweiter Datensatz entstehen.
        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests', [
            'eventIdentifier' => $scheduleId,
            'memberId' => $memberId,
        ], $headers);
        self::assertResponseStatusCodeSame(422);
    }

    public function testManuallyAddingAnUnknownMemberOrEventFails(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];

        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember('Erika', 'Musterfrau', 'erika@example.test', '1990-06-15'), $headers);
        self::assertResponseStatusCodeSame(201);
        $memberId = $this->responseData()['id'];
        self::assertIsString($memberId);

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

        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests', [
            'eventIdentifier' => 'unbekannte-veranstaltung',
            'memberId' => $memberId,
        ], $headers);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests', [
            'eventIdentifier' => $scheduleId,
            'memberId' => 'unbekanntes-mitglied',
        ], $headers);
        self::assertResponseStatusCodeSame(404);

        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests', [
            'eventIdentifier' => '',
            'memberId' => $memberId,
        ], $headers);
        self::assertResponseStatusCodeSame(400);
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
            email: 'event-help-manual-add-admin@example.test',
            displayName: 'Events Admin',
            roles: [Role::SuperAdmin],
            moduleAccess: [
                new ModuleAccess(CmsModule::Events, ModuleRole::Editor),
                new ModuleAccess(CmsModule::Activities, ModuleRole::Editor),
                new ModuleAccess(CmsModule::EventHelpers, ModuleRole::Editor),
                new ModuleAccess(CmsModule::Members, ModuleRole::Editor),
            ],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login-requests', ['email' => 'event-help-manual-add-admin@example.test']);
        $this->client->jsonRequest('POST', '/api/auth/v1/login', ['token' => FixedSecureTokenGenerator::TOKEN]);
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
