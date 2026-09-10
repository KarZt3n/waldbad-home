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

final class SettingsPinManagementWorkflowTest extends WebTestCase
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
        $entityManager->persist(new ContributionRateEntity(
            id: 'rate-individual-senior', category: 'individual_senior', label: 'Einzelperson über 21 Jahre',
            amountCents: 5000, period: 'yearly', personGroup: 'individual', minAge: 21, maxAge: null,
            pendingLabel: null,
            pendingAmountCents: null,
            pendingPeriod: null,
            pendingPersonGroup: null,
            pendingMinAge: null,
            pendingMaxAge: null,
            pendingValidFrom: null,
        ));
        $entityManager->persist(new MemberNumberSequenceEntity(id: 1, nextValue: 1));
        $entityManager->flush();
    }

    public function testNonAdministratorIsDeniedAccessToPinSettings(): void
    {
        $csrfToken = $this->loginAsEditor();

        $this->client->request('GET', '/api/admin/v1/pin-settings', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorCanSetAPinAndProtectAnAction(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $this->client->request('GET', '/api/admin/v1/pin-settings', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $initial = $this->responseData();
        self::assertFalse($this->bool($initial, 'globalPinIsSet'));
        self::assertSame([], $initial['protectedActions']);
        $initialActions = $this->arrayList($initial, 'availableActions');
        self::assertNotEmpty($initialActions);
        self::assertFalse($this->bool($this->firstEntry($initialActions), 'hasOwnPin'));

        // Ohne jeden PIN kann keine Aktion geschützt werden.
        $this->client->jsonRequest('PUT', '/api/admin/v1/pin-settings/protected-actions', ['protectedActions' => ['members.delete']], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('PUT', '/api/admin/v1/pin-settings/pin', ['pin' => '1234'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->bool($this->responseData(), 'globalPinIsSet'));

        $this->client->jsonRequest('PUT', '/api/admin/v1/pin-settings/protected-actions', ['protectedActions' => ['members.delete']], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertSame(['members.delete'], $this->responseData()['protectedActions']);

        $this->client->jsonRequest('POST', '/api/admin/v1/pin-settings/verify', ['action' => 'members.delete', 'pin' => '1234'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->responseData()['valid']);

        $this->client->jsonRequest('POST', '/api/admin/v1/pin-settings/verify', ['action' => 'members.delete', 'pin' => '9999'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testActionCanHaveItsOwnPinOverridingTheGlobalOne(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $this->client->jsonRequest('PUT', '/api/admin/v1/pin-settings/pin', ['pin' => '1111'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $this->client->jsonRequest('PUT', '/api/admin/v1/pin-settings/protected-actions', ['protectedActions' => ['members.delete']], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('PUT', '/api/admin/v1/pin-settings/actions/members.delete/pin', ['pin' => '2222'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $withOwnPin = $this->responseData();
        $membersDeleteEntry = $this->entryByKey($this->arrayList($withOwnPin, 'availableActions'), 'members.delete');
        self::assertTrue($this->bool($membersDeleteEntry, 'hasOwnPin'));

        // Der globale PIN „1111“ greift für „members.delete“ jetzt nicht mehr.
        $this->client->jsonRequest('POST', '/api/admin/v1/pin-settings/verify', ['action' => 'members.delete', 'pin' => '1111'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);
        $this->client->jsonRequest('POST', '/api/admin/v1/pin-settings/verify', ['action' => 'members.delete', 'pin' => '2222'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();

        // Eigenen PIN wieder entfernen: erlaubt, solange der globale PIN „1111“ als Rückfall
        // bestehen bleibt (das Verweigern ohne Rückfall ist bereits in ClearActionPinUseCaseTest
        // abgedeckt). Danach greift für „members.delete“ wieder der globale PIN.
        $this->client->request('DELETE', '/api/admin/v1/pin-settings/actions/members.delete/pin', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $afterClearing = $this->responseData();
        $membersDeleteEntryAfterClearing = $this->entryByKey($this->arrayList($afterClearing, 'availableActions'), 'members.delete');
        self::assertFalse($this->bool($membersDeleteEntryAfterClearing, 'hasOwnPin'));
        $this->client->jsonRequest('POST', '/api/admin/v1/pin-settings/verify', ['action' => 'members.delete', 'pin' => '1111'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
    }

    public function testDeletingAMemberRequiresTheCorrectPinOnceProtected(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $this->client->jsonRequest('PUT', '/api/admin/v1/pin-settings/pin', ['pin' => '1234'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $this->client->jsonRequest('PUT', '/api/admin/v1/pin-settings/protected-actions', ['protectedActions' => ['members.delete']], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember(), ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $memberId = $this->string($this->responseData(), 'id');

        // Ohne PIN: abgelehnt, Mitglied bleibt bestehen.
        $this->client->request('DELETE', '/api/admin/v1/members/'.$memberId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('GET', '/api/admin/v1/members/'.$memberId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();

        // Mit falschem PIN: ebenfalls abgelehnt.
        $this->client->jsonRequest('DELETE', '/api/admin/v1/members/'.$memberId, ['pin' => '0000'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);

        // Mit korrektem PIN: erfolgreich.
        $this->client->jsonRequest('DELETE', '/api/admin/v1/members/'.$memberId, ['pin' => '1234'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/api/admin/v1/members/'.$memberId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeletingAMemberNeedsNoPinWhenNotProtected(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember(), ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $memberId = $this->string($this->responseData(), 'id');

        $this->client->request('DELETE', '/api/admin/v1/members/'.$memberId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validMember(): array
    {
        return [
            'primaryMemberNumber' => null,
            'salutation' => 'ms',
            'lastName' => 'Musterfrau',
            'firstName' => 'Erika',
            'birthDate' => '1990-06-15',
            'street' => 'Kirchanger 14',
            'postalCode' => '14822',
            'city' => 'Borkheide',
            'email' => 'erika@example.test',
            'phone' => null,
            'familyRole' => 'none',
            'joinedAt' => '2026-01-01',
            'leftAt' => null,
            'active' => true,
            'function' => 'member',
            'accountHolder' => 'Erika Musterfrau',
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

    private function loginAsSuperAdmin(): string
    {
        return $this->createUserAndLogin('pin-settings-admin@example.test', [Role::SuperAdmin], [
            new ModuleAccess(CmsModule::Members, ModuleRole::Editor),
        ]);
    }

    /**
     * Editor mit vollem Modulzugriff, aber ohne Admin-/Super-Admin-Rolle — das Einstellungen-Modul
     * ist bewusst nicht über das reguläre Modul-Rechtesystem erreichbar (siehe
     * `AdminPinSettingsController`).
     */
    private function loginAsEditor(): string
    {
        return $this->createUserAndLogin('pin-settings-editor@example.test', [], [
            new ModuleAccess(CmsModule::Members, ModuleRole::Editor),
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
    private function firstEntry(array $entries): array
    {
        self::assertNotEmpty($entries);
        self::assertIsArray($entries[0]);

        return $entries[0];
    }

    /**
     * Sucht in einer Liste von Objekten (aus `availableActions`) den Eintrag mit `key` === $value.
     *
     * @param list<mixed> $entries
     * @return array<array-key, mixed>
     */
    private function entryByKey(array $entries, string $value): array
    {
        foreach ($entries as $entry) {
            self::assertIsArray($entry);
            if (($entry['key'] ?? null) === $value) {
                return $entry;
            }
        }
        self::fail(sprintf('Kein Eintrag mit key = "%s" gefunden.', $value));
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
