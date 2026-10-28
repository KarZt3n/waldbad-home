<?php

namespace App\Tests\Functional\Scenarios\Overview;

use App\Logic\IdentityAccess\User\Dto\CreateUserRequest;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\UseCase\CreateUserUseCase;
use App\Tests\Support\FixedSecureTokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminOverviewWorkflowTest extends WebTestCase
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

    public function testAdministratorSeesOpenTasksOfAllModules(): void
    {
        $this->submitContactRequest();
        $this->submitContactRequest();
        $this->client->jsonRequest('POST', '/api/public/v1/guestbook-entries', ['displayName' => 'Badegast', 'email' => '', 'message' => 'Schöner Tag am Teich.']);
        self::assertResponseStatusCodeSame(202);

        $this->login('overview-admin@example.test', [Role::SuperAdmin], [new ModuleAccess(CmsModule::Pages, ModuleRole::Publisher)]);
        $this->client->request('GET', '/api/admin/v1/overview');
        self::assertResponseIsSuccessful();
        $tasks = $this->tasksByType();

        self::assertSame([
            'membership_applications',
            'member_messages',
            'contact_requests',
            'guestbook_entries',
            'sauna_bookings',
            'sauna_duties',
            'event_help_requests',
            'pages_in_review',
            'pages_with_unpublished_changes',
        ], array_keys($tasks));
        self::assertSame(2, $tasks['contact_requests']['count']);
        self::assertIsString($tasks['contact_requests']['earliestAt']);
        self::assertSame(1, $tasks['guestbook_entries']['count']);
        self::assertSame(0, $tasks['membership_applications']['count']);
        self::assertNull($tasks['membership_applications']['earliestAt']);
        self::assertSame('rental_sauna', $tasks['sauna_bookings']['module']);
        self::assertSame(0, $tasks['sauna_duties']['count']);
    }

    public function testOnlyUnlockedModulesAreCounted(): void
    {
        $this->submitContactRequest();
        $this->login('overview-guestbook@example.test', [], [new ModuleAccess(CmsModule::Guestbook, ModuleRole::Viewer)]);

        $this->client->request('GET', '/api/admin/v1/overview');
        self::assertResponseIsSuccessful();

        self::assertSame(['guestbook_entries'], array_keys($this->tasksByType()));
    }

    public function testOverviewRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/v1/overview');

        self::assertResponseStatusCodeSame(401);
    }

    private function submitContactRequest(): void
    {
        $this->client->jsonRequest('POST', '/api/public/v1/contact-requests', [
            'name' => 'Erika Musterfrau',
            'email' => 'erika@example.test',
            'subject' => 'Öffnungszeit',
            'message' => 'Wann beginnt die Saison?',
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(202);
    }

    /**
     * @param list<Role> $roles
     * @param list<ModuleAccess> $moduleAccess
     */
    private function login(string $email, array $roles, array $moduleAccess): void
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(email: $email, displayName: 'Übersicht', roles: $roles, moduleAccess: $moduleAccess));
        $this->client->jsonRequest('POST', '/api/auth/v1/login-requests', ['email' => $email]);
        $this->client->jsonRequest('POST', '/api/auth/v1/login', ['token' => FixedSecureTokenGenerator::TOKEN]);
        self::assertResponseIsSuccessful();
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function tasksByType(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsArray($data['items']);
        $tasks = [];
        foreach ($data['items'] as $task) {
            self::assertIsArray($task);
            self::assertIsString($task['type']);
            $tasks[$task['type']] = $task;
        }

        return $tasks;
    }
}
