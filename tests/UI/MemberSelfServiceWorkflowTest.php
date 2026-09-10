<?php

namespace App\Tests\UI;

use App\Logic\IdentityAccess\User\Dto\CreateUserRequest;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\UseCase\CreateUserUseCase;
use App\Data\Membership\ContributionRate\Entity\ContributionRateEntity;
use App\Data\Membership\Member\Entity\MemberNumberSequenceEntity;
use App\Tests\Support\FixedAccessPasswordGenerator;
use App\Tests\Support\FixedSecureTokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-End-Abdeckung für „Meine Mitgliedschaft" (`PublicMemberAccessController`,
 * `AdminMemberMessageController`): Zugang per E-Mail anfordern → über den (im Testumfeld über
 * `FixedSecureTokenGenerator`/`FixedAccessPasswordGenerator` vorhersagbaren) Token + Passwort die
 * eigenen Daten einsehen → eine Nachricht senden → die Nachricht im Admin-Bereich wiederfinden.
 */
final class MemberSelfServiceWorkflowTest extends WebTestCase
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
        $this->seedContributionRates($entityManager);
        $entityManager->persist(new MemberNumberSequenceEntity(id: 1, nextValue: 1));
        $entityManager->flush();
    }

    /**
     * `SchemaTool::createSchema()` baut das Schema direkt aus den Entity-Mappings auf und führt
     * dabei keine Migrationen aus — die in `Version20260907120100` per `postUp()` eingefügten
     * Startwerte der Beitragssätze fehlen daher und müssen für die Tests hier nachgebildet werden
     * (sonst schlägt die automatische Beitragsermittlung bei der Mitgliedsanlage fehl).
     */
    private function seedContributionRates(EntityManagerInterface $entityManager): void
    {
        $rates = [
            ['individual_junior', 'Einzelperson bis 21 Jahre', 3000, 'yearly', 'individual', null, 20],
            ['individual_senior', 'Einzelperson über 21 Jahre', 5000, 'yearly', 'individual', 21, null],
            ['family_adult', 'Familie: Elternteil', 4000, 'yearly', 'family', null, null],
            ['family_child_paying', 'Familie: Kind (zahlend)', 3000, 'yearly', 'family', 4, 20],
            ['family_child_exempt', 'Familie: Kind (beitragsfrei)', 0, 'yearly', 'family', null, 3],
            ['work_assignment_surcharge', 'Arbeitseinsatz', 1500, 'yearly', null, 8, 65],
        ];
        foreach ($rates as $index => [$category, $label, $amountCents, $period, $personGroup, $minAge, $maxAge]) {
            $entityManager->persist(new ContributionRateEntity(
                id: sprintf('rate-%d', $index + 1),
                category: $category,
                label: $label,
                amountCents: $amountCents,
                period: $period,
                personGroup: $personGroup,
                minAge: $minAge,
                maxAge: $maxAge,
                pendingLabel: null,
                pendingAmountCents: null,
                pendingPeriod: null,
                pendingPersonGroup: null,
                pendingMinAge: null,
                pendingMaxAge: null,
                pendingValidFrom: null,
            ));
        }
    }

    public function testFullFlowFromRequestingAccessToAnAdminSeeingTheMessage(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();
        $memberId = $this->createMember($csrfToken, 'erika@example.test');
        $this->client->request('GET', '/');

        // 1. Zugang anfordern (öffentlich, keine Anmeldung).
        $this->client->jsonRequest('POST', '/api/public/v1/member-access/tokens', ['email' => 'erika@example.test', 'birthDate' => '1990-06-15']);
        self::assertResponseStatusCodeSame(202);

        // 2. Den (im Testumfeld festen) Token + Passwort einlösen und die eigenen Daten einsehen.
        $this->client->jsonRequest('POST', '/api/public/v1/member-access/sessions', [
            'token' => FixedSecureTokenGenerator::TOKEN,
            'password' => FixedAccessPasswordGenerator::PASSWORD,
        ]);
        self::assertResponseIsSuccessful();
        $session = $this->responseData();
        self::assertSame('erika@example.test', $this->string($session, 'email'));
        $members = $this->arrayList($session, 'members');
        self::assertCount(1, $members);
        $member = $this->entry($members[0]);
        self::assertSame($memberId, $this->string($member, 'id'));
        self::assertSame('Erika', $this->string($member, 'firstName'));
        self::assertSame('Musterfrau', $this->string($member, 'lastName'));
        // Sensible Bankdaten dürfen über „Meine Mitgliedschaft" nicht sichtbar sein.
        self::assertArrayNotHasKey('iban', $member);
        self::assertArrayNotHasKey('accountHolder', $member);

        // 3. Eine Nachricht senden.
        $this->client->jsonRequest('POST', '/api/public/v1/member-access/messages', [
            'token' => FixedSecureTokenGenerator::TOKEN,
            'password' => FixedAccessPasswordGenerator::PASSWORD,
            'memberId' => $memberId,
            'message' => 'Meine Telefonnummer hat sich geändert.',
        ]);
        self::assertResponseStatusCodeSame(202);

        // 4. Die Nachricht taucht im Admin-Bereich auf.
        $this->client->request('GET', '/api/admin/v1/member-messages', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $messages = $this->arrayList($this->responseData(), 'items');
        self::assertCount(1, $messages);
        $message = $this->entry($messages[0]);
        self::assertSame($memberId, $this->string($message, 'memberId'));
        self::assertSame('Erika Musterfrau', $this->string($message, 'memberName'));
        self::assertSame('Meine Telefonnummer hat sich geändert.', $this->string($message, 'message'));
        self::assertSame('new', $this->string($message, 'status'));
    }

    /**
     * Kein Unterschied im Verhalten, ob die E-Mail-Adresse zu einem Mitglied gehört oder nicht —
     * sonst ließe sich darüber herausfinden, welche Adressen als Mitglied existieren.
     */
    public function testRequestingAccessForAnUnknownEmailRespondsTheSameWayButCreatesNoUsableToken(): void
    {
        $this->client->jsonRequest('POST', '/api/public/v1/member-access/tokens', ['email' => 'unknown@example.test', 'birthDate' => '1990-06-15']);
        self::assertResponseStatusCodeSame(202);
        $knownEmailMessage = $this->string($this->responseData(), 'message');

        $csrfToken = $this->loginAsSuperAdmin();
        $this->createMember($csrfToken, 'erika@example.test');
        $this->client->request('GET', '/');
        $this->client->jsonRequest('POST', '/api/public/v1/member-access/tokens', ['email' => 'erika@example.test', 'birthDate' => '1990-06-15']);
        self::assertResponseStatusCodeSame(202);
        self::assertSame($knownEmailMessage, $this->string($this->responseData(), 'message'));

        // Da für die unbekannte Adresse nie ein Token gespeichert wurde, schlägt das Einlösen fehl.
        $this->client->jsonRequest('POST', '/api/public/v1/member-access/sessions', [
            'token' => 'never-issued-token',
            'password' => FixedAccessPasswordGenerator::PASSWORD,
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testResolvingAnUnknownTokenIsRejected(): void
    {
        $this->client->jsonRequest('POST', '/api/public/v1/member-access/sessions', [
            'token' => 'does-not-exist',
            'password' => FixedAccessPasswordGenerator::PASSWORD,
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Zweiter Faktor neben dem Token (siehe `MemberAccessToken`): ein gültiger Token mit falschem
     * Passwort wird genauso abgelehnt wie ein unbekannter Token — kein Unterschied erkennbar,
     * welcher der beiden Faktoren nicht gepasst hat.
     */
    public function testResolvingWithAWrongPasswordIsRejected(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();
        $this->createMember($csrfToken, 'erika@example.test');
        $this->client->request('GET', '/');
        $this->client->jsonRequest('POST', '/api/public/v1/member-access/tokens', ['email' => 'erika@example.test', 'birthDate' => '1990-06-15']);
        self::assertResponseStatusCodeSame(202);

        $this->client->jsonRequest('POST', '/api/public/v1/member-access/sessions', [
            'token' => FixedSecureTokenGenerator::TOKEN,
            'password' => 'wrong-password',
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Der Token bestätigt nur den Zugriff auf die E-Mail-Adresse, nicht automatisch auf jedes
     * einzelne Mitglied dahinter.
     */
    public function testSendingAMessageForAMemberNotReachableThroughTheTokenIsRejected(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();
        $this->createMember($csrfToken, 'erika@example.test');
        $this->client->request('GET', '/');
        $this->client->jsonRequest('POST', '/api/public/v1/member-access/tokens', ['email' => 'erika@example.test', 'birthDate' => '1990-06-15']);
        self::assertResponseStatusCodeSame(202);

        $this->client->jsonRequest('POST', '/api/public/v1/member-access/messages', [
            'token' => FixedSecureTokenGenerator::TOKEN,
            'password' => FixedAccessPasswordGenerator::PASSWORD,
            'memberId' => 'someone-elses-member-id',
            'message' => 'Text',
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testNonAdministratorIsDeniedAccessToMemberMessages(): void
    {
        $csrfToken = $this->createUserAndLogin('member-messages-editor@example.test', [], [
            new ModuleAccess(CmsModule::MembershipApplications, ModuleRole::Editor),
        ]);

        $this->client->request('GET', '/api/admin/v1/member-messages', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(403);
    }

    private function createMember(string $csrfToken, string $email): string
    {
        $this->client->jsonRequest('POST', '/api/admin/v1/members', [
            'primaryMemberNumber' => null,
            'salutation' => 'ms',
            'lastName' => 'Musterfrau',
            'firstName' => 'Erika',
            'birthDate' => '1990-06-15',
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
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);

        return $this->string($this->responseData(), 'id');
    }

    private function loginAsSuperAdmin(): string
    {
        return $this->createUserAndLogin('member-access-admin@example.test', [Role::SuperAdmin], [
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
     * @return list<mixed>
     */
    private function arrayList(array $data, string $key): array
    {
        self::assertIsArray($data[$key]);
        self::assertTrue(array_is_list($data[$key]));

        return $data[$key];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function entry(mixed $value): array
    {
        self::assertIsArray($value);

        return $value;
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
