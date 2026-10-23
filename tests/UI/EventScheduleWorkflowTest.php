<?php

namespace App\Tests\UI;

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

final class EventScheduleWorkflowTest extends WebTestCase
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
    }

    public function testWorkAssignmentCanBeManagedAndAcceptsHelperSignUps(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];

        $this->client->jsonRequest('POST', '/api/admin/v1/event-activities', [
            'name' => 'Rasen mähen', 'description' => '', 'active' => true, 'preselected' => true,
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $activityId = $this->responseData()['id'];
        self::assertIsString($activityId);

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
            'activities' => [[
                'activityId' => $activityId,
                'requiredHelpers' => 2,
                'selectable' => true,
                'time' => '09:30',
                'meetTime' => '09:15',
                'meetPlace' => 'Haupteingang',
                'remark' => 'Bitte Handschuhe mitbringen.',
            ]],
            'callToActions' => [],
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $created = $this->responseData();
        $scheduleId = $created['id'];
        self::assertIsString($scheduleId);
        self::assertSame('work_assignment', $created['kind']);
        $createdActivities = $created['activities'];
        self::assertIsArray($createdActivities);
        self::assertIsArray($createdActivities[0]);
        self::assertSame('Haupteingang', $createdActivities[0]['meetPlace']);

        $this->client->request('GET', '/api/admin/v1/events');
        self::assertResponseIsSuccessful();
        $adminList = $this->responseData();
        self::assertSame(1, $adminList['total']);

        $this->client->request('GET', '/api/public/v1/events?kind=work_assignment');
        self::assertResponseIsSuccessful();
        $publicList = $this->responseData();
        self::assertSame(1, $publicList['total']);
        $publicItems = $publicList['items'];
        self::assertIsArray($publicItems);
        self::assertIsArray($publicItems[0]);
        self::assertSame('Frühjahrsputz', $publicItems[0]['title']);

        $this->client->request('GET', '/api/public/v1/event-activities/'.$scheduleId);
        self::assertResponseIsSuccessful();
        $availability = $this->availabilityItems();
        self::assertCount(1, $availability);
        self::assertSame('09:15', $availability[0]['meetTime']);
        self::assertSame(0, $availability[0]['registeredHelpers']);
        self::assertTrue($availability[0]['selectable']);
        self::assertTrue($availability[0]['preselected']);

        $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', [
            'eventIdentifier' => $scheduleId,
            'firstName' => 'Helfer',
            'lastName' => 'Testfall',
            'birthDate' => '1990-06-15',
            'message' => '',
            'activityIds' => [$activityId],
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(202);

        $this->client->request('GET', '/api/public/v1/event-activities/'.$scheduleId);
        self::assertSame(1, $this->availabilityItems()[0]['registeredHelpers']);

        $this->client->jsonRequest('PUT', '/api/admin/v1/events/'.$scheduleId, [
            'title' => 'Frühjahrsputz (verschoben)',
            'date' => $today,
            'time' => '10:00',
            'content' => '',
            'helpEnabled' => true,
            'helpButtonLabel' => null,
            'visible' => true,
            'activities' => [],
            'callToActions' => [],
        ], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame('Frühjahrsputz (verschoben)', $this->responseData()['title']);

        $this->client->request('DELETE', '/api/admin/v1/events/'.$scheduleId, [], [], $headers);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/admin/v1/events');
        self::assertSame(0, $this->responseData()['total']);
    }

    /** 24 Stunden nach Beginn nimmt die öffentliche Helferanmeldung keine Anmeldungen mehr an. */
    public function testHelperSignUpIsRejectedMoreThan24HoursAfterTheStart(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $this->client->jsonRequest('POST', '/api/admin/v1/events', [
            'kind' => 'work_assignment',
            'title' => 'Herbstputz',
            'date' => (new \DateTimeImmutable('-2 days'))->format('Y-m-d'),
            'time' => '09:00',
            'content' => '',
            'helpEnabled' => true,
            'helpButtonLabel' => null,
            'visible' => true,
            'activities' => [],
            'callToActions' => [],
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $scheduleId = $this->responseData()['id'];
        self::assertIsString($scheduleId);

        $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', [
            'eventIdentifier' => $scheduleId,
            'firstName' => 'Helfer',
            'lastName' => 'Zuspät',
            'birthDate' => '1990-06-15',
            'message' => '',
            'activityIds' => [],
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(422);
        $error = $this->responseData()['error'];
        self::assertIsArray($error);
        self::assertSame('Die Helferanmeldung für diese Veranstaltung ist geschlossen.', $error['message']);
    }

    public function testListedActivitiesAreNotSelectableAndNeedNoHelperCount(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];

        $this->client->jsonRequest('POST', '/api/admin/v1/event-activities', [
            'name' => 'Außenanlagen', 'description' => 'Rasenflächen harken, Bänke streichen', 'active' => true, 'preselected' => true,
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $created = $this->responseData();
        self::assertFalse($created['defaultSelectable']);
        $listedActivityId = $created['id'];
        self::assertIsString($listedActivityId);

        $this->client->jsonRequest('POST', '/api/admin/v1/events', [
            'kind' => 'work_assignment',
            'title' => 'Arbeitseinsatz',
            'date' => (new \DateTimeImmutable())->format('Y-m-d'),
            'time' => '09:00',
            'content' => '',
            'helpEnabled' => true,
            'helpButtonLabel' => null,
            'visible' => true,
            'activities' => [['activityId' => $listedActivityId, 'requiredHelpers' => null, 'selectable' => false]],
            'callToActions' => [],
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $scheduleId = $this->responseData()['id'];
        self::assertIsString($scheduleId);

        $this->client->request('GET', '/api/public/v1/event-activities/'.$scheduleId);
        $availability = $this->availabilityItems();
        self::assertCount(1, $availability);
        self::assertFalse($availability[0]['selectable']);
        self::assertNull($availability[0]['requiredHelpers']);
        self::assertFalse($availability[0]['preselected']);

        $signUp = [
            'eventIdentifier' => $scheduleId,
            'firstName' => 'Helfer',
            'lastName' => 'Auflistung',
            'birthDate' => '1990-06-15',
            'message' => '',
            'activityIds' => [$listedActivityId],
            'privacyAccepted' => true,
        ];
        $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', $signUp);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', [...$signUp, 'activityIds' => []]);
        self::assertResponseStatusCodeSame(202);
    }

    private function loginAsAdmin(): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: 'events-admin@example.test',
            displayName: 'Events Admin',
            roles: [Role::SuperAdmin],
            moduleAccess: [
                new ModuleAccess(CmsModule::Events, ModuleRole::Editor),
                new ModuleAccess(CmsModule::Activities, ModuleRole::Editor),
            ],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login-requests', ['email' => 'events-admin@example.test']);
        $this->client->jsonRequest('POST', '/api/auth/v1/login', ['token' => FixedSecureTokenGenerator::TOKEN]);
        self::assertResponseIsSuccessful();
        $login = $this->responseData();
        self::assertIsString($login['csrfToken']);

        return $login['csrfToken'];
    }

    /** @return list<array<string, mixed>> */
    private function availabilityItems(): array
    {
        $items = $this->responseData()['items'];
        self::assertIsArray($items);
        $mapped = [];
        foreach ($items as $item) {
            self::assertIsArray($item);
            $entry = [];
            foreach ($item as $key => $value) {
                self::assertIsString($key);
                $entry[$key] = $value;
            }
            $mapped[] = $entry;
        }

        return $mapped;
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
