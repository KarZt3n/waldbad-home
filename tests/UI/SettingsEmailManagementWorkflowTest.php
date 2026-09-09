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

final class SettingsEmailManagementWorkflowTest extends WebTestCase
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

    public function testNonAdministratorIsDeniedAccessToEmailSettings(): void
    {
        $csrfToken = $this->loginAsEditor();

        $this->client->request('GET', '/api/admin/v1/email-settings', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorCanConfigureConnectionAndNotificationRecipients(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $this->client->request('GET', '/api/admin/v1/email-settings', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $initial = $this->responseData();
        self::assertFalse($this->bool($initial, 'configured'));
        self::assertFalse($this->bool($initial, 'passwordIsSet'));
        $presets = $this->arrayList($initial, 'providerPresets');
        self::assertNotEmpty($presets);
        $events = $this->arrayList($initial, 'notificationEvents');
        self::assertSame('membership_application_submitted', $this->string($this->firstEntry($events), 'key'));

        $this->client->jsonRequest('PUT', '/api/admin/v1/email-settings', [
            'provider' => 'google',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'verein@example.test',
            'password' => 'app-password',
            'fromAddress' => 'verein@example.test',
            'fromName' => 'Waldbad Borkheide',
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $updated = $this->responseData();
        self::assertTrue($this->bool($updated, 'configured'));
        self::assertTrue($this->bool($updated, 'passwordIsSet'));
        self::assertSame('smtp.gmail.com', $updated['host']);

        // Ungültige E-Mail-Adresse wird abgelehnt.
        $this->client->jsonRequest('PUT', '/api/admin/v1/email-settings/notifications/membership_application_submitted', [
            'recipients' => ['not-an-email'],
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('PUT', '/api/admin/v1/email-settings/notifications/membership_application_submitted', [
            'recipients' => ['vorstand@example.test', 'kasse@example.test'],
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $withRecipients = $this->responseData();
        $recipients = $this->arrayData($withRecipients, 'notificationRecipients');
        self::assertSame(['vorstand@example.test', 'kasse@example.test'], $recipients['membership_application_submitted']);
    }

    public function testSubmittingAMembershipApplicationStillSucceedsWhenTheNotificationCannotBeSent(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        // Absichtlich nicht erreichbarer Mailserver: die Antragsannahme selbst darf davon nicht
        // beeinträchtigt werden (siehe NotificationMailer, „best effort“).
        $this->client->jsonRequest('PUT', '/api/admin/v1/email-settings', [
            'provider' => 'custom',
            'host' => '127.0.0.1',
            'port' => 1,
            'username' => null,
            'password' => null,
            'fromAddress' => 'verein@example.test',
            'fromName' => null,
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $this->client->jsonRequest('PUT', '/api/admin/v1/email-settings/notifications/membership_application_submitted', [
            'recipients' => ['vorstand@example.test'],
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('POST', '/api/public/v1/membership-applications', $this->validApplication());
        self::assertResponseStatusCodeSame(202);
    }

    public function testTestEmailReportsATransportFailureInsteadOfSwallowingIt(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $this->client->jsonRequest('PUT', '/api/admin/v1/email-settings', [
            'provider' => 'custom',
            'host' => '127.0.0.1',
            'port' => 1,
            'username' => null,
            'password' => null,
            'fromAddress' => 'verein@example.test',
            'fromName' => null,
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('POST', '/api/admin/v1/email-settings/test', ['to' => 'admin@example.test'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @return array<string, mixed>
     */
    private function validApplication(): array
    {
        return [
            'membershipType' => 'individual',
            'applicants' => [[
                'salutation' => 'ms',
                'firstName' => 'Erika',
                'lastName' => 'Musterfrau',
                'birthDate' => '1990-06-15',
                'street' => 'Kirchanger',
                'houseNumber' => '14',
                'postalCode' => '14822',
                'city' => 'Borkheide',
                'phone' => null,
                'email' => 'erika@example.test',
            ]],
            'accountHolder' => 'Erika Musterfrau',
            'iban' => 'DE89370400440532013000',
            'bankName' => null,
            'signerName' => 'Erika Musterfrau',
            'termsAccepted' => true,
            'privacyAccepted' => true,
            'sepaAccepted' => true,
            'emailConsent' => true,
        ];
    }

    private function loginAsSuperAdmin(): string
    {
        return $this->createUserAndLogin('email-settings-admin@example.test', [Role::SuperAdmin], [
            new ModuleAccess(CmsModule::MembershipApplications, ModuleRole::Editor),
        ]);
    }

    /**
     * Editor mit vollem Modulzugriff, aber ohne Admin-/Super-Admin-Rolle — das Einstellungen-Modul
     * ist bewusst nicht über das reguläre Modul-Rechtesystem erreichbar (siehe
     * `AdminEmailSettingsController`).
     */
    private function loginAsEditor(): string
    {
        return $this->createUserAndLogin('email-settings-editor@example.test', [], [
            new ModuleAccess(CmsModule::MembershipApplications, ModuleRole::Editor),
        ]);
    }

    /**
     * @param list<Role> $roles
     * @param list<ModuleAccess> $moduleAccess
     */
    private function createUserAndLogin(string $email, array $roles, array $moduleAccess): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: $email,
            displayName: 'Test User',
            plainPassword: 'Ein-sicheres-Testpasswort-2026',
            roles: $roles,
            moduleAccess: $moduleAccess,
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login', [
            'email' => $email,
            'password' => 'Ein-sicheres-Testpasswort-2026',
        ]);
        self::assertResponseIsSuccessful();

        return $this->string($this->responseData(), 'csrfToken');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function string(array $data, string $key): string
    {
        self::assertIsString($data[$key]);

        return $data[$key];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function bool(array $data, string $key): bool
    {
        self::assertIsBool($data[$key]);

        return $data[$key];
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function arrayData(array $data, string $key): array
    {
        self::assertIsArray($data[$key]);

        return $data[$key];
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<mixed>
     */
    private function arrayList(array $data, string $key): array
    {
        self::assertIsArray($data[$key]);
        self::assertTrue(array_is_list($data[$key]));

        return $data[$key];
    }

    /**
     * @param list<mixed> $entries
     * @return array<array-key, mixed>
     */
    private function firstEntry(array $entries): array
    {
        self::assertNotEmpty($entries);
        self::assertIsArray($entries[0]);

        return $entries[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(): array
    {
        $content = $this->client->getResponse()->getContent();
        if (!is_string($content)) {
            throw new \LogicException('Die Testantwort enthält keinen lesbaren Inhalt.');
        }
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_is_list($data)) {
            throw new \LogicException('Die Testantwort enthält kein JSON-Objekt.');
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
