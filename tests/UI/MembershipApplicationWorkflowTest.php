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

final class MembershipApplicationWorkflowTest extends WebTestCase
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
        // Für die Freigabe (Mitglied anlegen) berechnet `MemberOnboardingOrchestrator` sofort den
        // Beitrag; `SchemaTool::createSchema()` führt keine Migrationen aus, daher fehlen die per
        // `postUp()` eingefügten Standard-Beitragssätze ohne diese Seed-Daten.
        $entityManager->persist(new ContributionRateEntity(
            id: 'rate-individual-senior',
            category: 'individual_senior',
            label: 'Einzelperson über 21 Jahre',
            amountCents: 5000,
            period: 'yearly',
            personGroup: 'individual',
            minAge: 21,
            maxAge: null,
        ));
        $entityManager->persist(new MemberNumberSequenceEntity(id: 1, nextValue: 1));
        $entityManager->flush();
    }

    public function testApplicationCanBeSubmittedAndReviewedByAnAdmin(): void
    {
        $this->client->jsonRequest('POST', '/api/public/v1/membership-applications', $this->validApplication());
        self::assertResponseStatusCodeSame(202);
        $submitted = $this->responseData();
        self::assertIsString($submitted['id']);
        self::assertNull($submitted['releasedAt']);
        self::assertNull($submitted['rejectedAt']);
        $applicationId = $submitted['id'];

        $csrfToken = $this->loginAsSuperAdmin();
        $this->client->request('GET', '/api/admin/v1/membership-applications');
        self::assertResponseIsSuccessful();
        $adminList = $this->responseData();
        self::assertSame(1, $adminList['total']);
        self::assertIsArray($adminList['items']);
        self::assertIsArray($adminList['items'][0]);
        $adminApplication = $adminList['items'][0];
        self::assertIsString($adminApplication['iban']);
        self::assertSame('DE89370400440532013000', $adminApplication['iban']);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('Der EntityManager ist im Testcontainer nicht verfügbar.');
        }
        $connection = $entityManager->getConnection();
        $storedIban = $connection->fetchOne(
            'SELECT iban FROM membership_application WHERE id = :id',
            ['id' => $applicationId],
        );
        self::assertSame('DE89370400440532013000', $storedIban);
        self::assertFalse(in_array(
            'iban_encrypted',
            array_keys($connection->createSchemaManager()->listTableColumns('membership_application')),
            true,
        ));

        $this->client->request('GET', '/api/admin/v1/membership-applications/'.$applicationId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $adminDetail = $this->responseData();
        self::assertSame($applicationId, $adminDetail['id']);
        self::assertIsArray($adminDetail['applicants']);
        self::assertIsArray($adminDetail['applicants'][0]);
        self::assertSame('ms', $adminDetail['applicants'][0]['salutation']);
    }

    public function testApplicationCanBeRejectedButNotAfterwardsReleasedOrRejectedAgain(): void
    {
        $this->client->jsonRequest('POST', '/api/public/v1/membership-applications', $this->validApplication());
        self::assertResponseStatusCodeSame(202);
        $applicationId = $this->responseData()['id'];

        $csrfToken = $this->loginAsSuperAdmin();
        $this->client->jsonRequest('POST', '/api/admin/v1/membership-applications/'.$applicationId.'/reject', [
            'reason' => 'Beitrittserklärung unvollständig.',
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $rejected = $this->responseData();
        self::assertIsString($rejected['rejectedAt']);
        self::assertSame('Beitrittserklärung unvollständig.', $rejected['rejectionReason']);
        self::assertNull($rejected['releasedAt']);

        $this->client->request('POST', '/api/admin/v1/membership-applications/'.$applicationId.'/release', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('POST', '/api/admin/v1/membership-applications/'.$applicationId.'/reject', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testReleasedApplicationCanNoLongerBeRejected(): void
    {
        $this->client->jsonRequest('POST', '/api/public/v1/membership-applications', $this->validApplication());
        self::assertResponseStatusCodeSame(202);
        $applicationId = $this->responseData()['id'];

        // `loginAsSuperAdmin()` gewährt kein Mitglieder-Modul; für die Freigabe (Mitglied anlegen)
        // wird zusätzlich `CmsModule::Members` benötigt.
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: 'membership-admin-with-members@example.test',
            displayName: 'Membership Admin',
            plainPassword: 'Ein-sicheres-Testpasswort-2026',
            roles: [Role::SuperAdmin],
            moduleAccess: [
                new ModuleAccess(CmsModule::MembershipApplications, ModuleRole::Editor),
                new ModuleAccess(CmsModule::Members, ModuleRole::Editor),
            ],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login', [
            'email' => 'membership-admin-with-members@example.test',
            'password' => 'Ein-sicheres-Testpasswort-2026',
        ]);
        self::assertResponseIsSuccessful();
        $csrfToken = $this->responseData()['csrfToken'];

        $this->client->request('POST', '/api/admin/v1/membership-applications/'.$applicationId.'/release', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertIsString($this->responseData()['releasedAt']);

        $this->client->request('POST', '/api/admin/v1/membership-applications/'.$applicationId.'/reject', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testMembershipExtensionCanBeStoredAsPageBlock(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();
        $this->client->jsonRequest('POST', '/api/admin/v1/pages', [
            'title' => 'Mitglied werden',
            'slug' => 'mitglied-werden-test',
            'navigationLabel' => 'Mitglied werden',
            'parentId' => null,
            'navigationPosition' => 0,
            'visible' => true,
            'showInNavigation' => true,
            'seoTitle' => 'Mitglied werden',
            'seoDescription' => null,
            'blocks' => [[
                'type' => 'extension',
                'content' => '',
                'extensionKey' => 'membership_application',
            ]],
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);

        self::assertResponseStatusCodeSame(201);
        $page = $this->responseData();
        self::assertIsArray($page['blocks']);
        self::assertIsArray($page['blocks'][0]);
        $block = $page['blocks'][0];
        self::assertSame('extension', $block['type']);
        self::assertSame('membership_application', $block['extensionKey']);
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
                'phone' => '+49 123 456789',
                'email' => 'erika@example.test',
            ]],
            'accountHolder' => 'Erika Musterfrau',
            'iban' => 'DE89370400440532013000',
            'bankName' => 'Testbank',
            'signerName' => 'Erika Musterfrau',
            'termsAccepted' => true,
            'privacyAccepted' => true,
            'sepaAccepted' => true,
            'emailConsent' => true,
        ];
    }

    private function loginAsSuperAdmin(): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: 'membership-admin@example.test',
            displayName: 'Membership Admin',
            plainPassword: 'Ein-sicheres-Testpasswort-2026',
            roles: [Role::SuperAdmin],
            moduleAccess: [
                new ModuleAccess(CmsModule::MembershipApplications, ModuleRole::Editor),
                new ModuleAccess(CmsModule::Pages, ModuleRole::Publisher),
            ],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login', [
            'email' => 'membership-admin@example.test',
            'password' => 'Ein-sicheres-Testpasswort-2026',
        ]);
        self::assertResponseIsSuccessful();
        $login = $this->responseData();
        self::assertIsString($login['csrfToken']);

        return $login['csrfToken'];
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
