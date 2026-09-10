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

/**
 * End-to-End-Abdeckung für Mailvorlagen (`AdminMailTemplateController`) und Signaturen
 * (`AdminMailSignatureController`): eine Signatur wird angelegt, einer Vorlage zugeordnet
 * (referenziert, nicht als Text kopiert) und muss beim Rendern (Vorschau) automatisch an den Text
 * angehängt werden — inklusive Platzhalterersetzung und dem Zurücksetzen der Zuordnung beim
 * „Auf Standard zurücksetzen“.
 */
final class SettingsMailTemplateWorkflowTest extends WebTestCase
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

    public function testNonAdministratorIsDeniedAccessToMailTemplatesAndSignatures(): void
    {
        $csrfToken = $this->loginAsEditor();

        $this->client->request('GET', '/api/admin/v1/mail-templates', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/api/admin/v1/mail-signatures', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Kernstück der Funktionalität: eine Signatur wird angelegt, einer Vorlage zugeordnet, und
     * sowohl der gespeicherte Zustand als auch die Vorschau zeigen sie an den Text angehängt — eine
     * spätere Änderung der Signatur (hier nicht mehr nötig zu testen, siehe
     * `MailTemplateRendererTest`) wirkt sich dadurch automatisch auf die Vorlage aus, ohne diese
     * einzeln anzupassen.
     */
    public function testAssigningASignatureToATemplateAppendsItInTheSavedStateAndPreview(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $this->client->jsonRequest('POST', '/api/admin/v1/mail-signatures', [
            'name' => 'Standard-Signatur',
            'body' => "Freundliche Grüße\nDas Waldbad-Team\n{{vereinsname}}",
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $signatureId = $this->string($this->responseData(), 'id');

        $this->client->jsonRequest('PUT', '/api/admin/v1/mail-templates/membership_application_approved', [
            'subject' => 'Willkommen {{vorname}}',
            'body' => 'Hallo {{vorname}}, willkommen!',
            'signatureId' => $signatureId,
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $updated = $this->firstEntry($this->arrayList($this->responseData(), 'items'), 'key', 'membership_application_approved');
        self::assertSame($signatureId, $updated['signatureId']);
        self::assertFalse($this->bool($updated, 'isDefault'));

        // Die Zuordnung bleibt auch nach einem erneuten GET bestehen (persistiert, nicht nur in der
        // Antwort auf das PUT).
        $this->client->request('GET', '/api/admin/v1/mail-templates', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $reloaded = $this->firstEntry($this->arrayList($this->responseData(), 'items'), 'key', 'membership_application_approved');
        self::assertSame($signatureId, $reloaded['signatureId']);

        $this->client->jsonRequest('POST', '/api/admin/v1/mail-templates/membership_application_approved/preview', [
            'subject' => 'Willkommen {{vorname}}',
            'body' => 'Hallo {{vorname}}, willkommen!',
            'signatureId' => $signatureId,
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $preview = $this->responseData();
        self::assertStringContainsString('Freundliche Grüße', $this->string($preview, 'text'));
        self::assertStringContainsString('Das Waldbad-Team', $this->string($preview, 'text'));
        // {{vereinsname}} in der Signatur wurde ebenfalls ersetzt, nicht nur im Hauptvorlagentext.
        self::assertStringNotContainsString('{{vereinsname}}', $this->string($preview, 'text'));
        self::assertStringContainsString('Freundliche Grüße', $this->string($preview, 'html'));

        // „Auf Standard zurücksetzen“ hebt auch die Signatur-Zuordnung wieder auf.
        $this->client->jsonRequest('POST', '/api/admin/v1/mail-templates/membership_application_approved/reset', [], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $reset = $this->firstEntry($this->arrayList($this->responseData(), 'items'), 'key', 'membership_application_approved');
        self::assertNull($reset['signatureId']);
        self::assertTrue($this->bool($reset, 'isDefault'));
    }

    public function testAssigningAnUnknownSignatureIsRejected(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $this->client->jsonRequest('PUT', '/api/admin/v1/mail-templates/membership_application_approved', [
            'subject' => 'Betreff',
            'body' => 'Text',
            'signatureId' => 'unknown-signature',
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testDeletingASignatureLeavesAnAssignedTemplateWorkingWithoutIt(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $this->client->jsonRequest('POST', '/api/admin/v1/mail-signatures', ['name' => 'Alt', 'body' => 'Alte Grüße'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $signatureId = $this->string($this->responseData(), 'id');

        $this->client->jsonRequest('PUT', '/api/admin/v1/mail-templates/membership_application_approved', [
            'subject' => 'Betreff',
            'body' => 'Hallo!',
            'signatureId' => $signatureId,
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();

        $this->client->request('DELETE', "/api/admin/v1/mail-signatures/{$signatureId}", server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(204);

        // Best effort: die Vorschau schlägt nicht fehl, sie lässt die gelöschte Signatur nur weg.
        $this->client->jsonRequest('POST', '/api/admin/v1/mail-templates/membership_application_approved/preview', [
            'subject' => 'Betreff',
            'body' => 'Hallo!',
            'signatureId' => $signatureId,
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertSame('Hallo!', $this->string($this->responseData(), 'text'));
    }

    private function loginAsSuperAdmin(): string
    {
        return $this->createUserAndLogin('mail-template-admin@example.test', [Role::SuperAdmin], [
            new ModuleAccess(CmsModule::MembershipApplications, ModuleRole::Editor),
        ]);
    }

    /**
     * Editor mit vollem Modulzugriff, aber ohne Admin-/Super-Admin-Rolle — Mailvorlagen und
     * Signaturen sind bewusst nicht über das reguläre Modul-Rechtesystem erreichbar (siehe
     * `AdminMailTemplateController`, `AdminMailSignatureController`).
     */
    private function loginAsEditor(): string
    {
        return $this->createUserAndLogin('mail-template-editor@example.test', [], [
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
    private function firstEntry(array $entries, string $key, string $value): array
    {
        foreach ($entries as $entry) {
            self::assertIsArray($entry);
            if (($entry[$key] ?? null) === $value) {
                return $entry;
            }
        }
        self::fail(sprintf('Kein Eintrag mit "%s" = "%s" gefunden.', $key, $value));
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
