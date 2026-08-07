<?php

namespace App\Tests\UI;

use App\Logic\IdentityAccess\User\Dto\CreateUserRequest;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\UseCase\CreateUserUseCase;
use App\Logic\Membership\Application\UseCase\ClaimMembershipApplicationsUseCase;
use App\Logic\Membership\Application\UseCase\CompleteMembershipApplicationUseCase;
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
    }

    public function testApplicationCanBeSubmittedReviewedAndTransferred(): void
    {
        $this->client->jsonRequest('POST', '/api/public/v1/membership-applications', $this->validApplication());
        self::assertResponseStatusCodeSame(202);
        $submitted = $this->responseData();
        self::assertSame('pending', $submitted['status']);
        self::assertIsString($submitted['id']);
        $applicationId = $submitted['id'];

        $csrfToken = $this->loginAsSuperAdmin();
        $this->client->request('GET', '/api/admin/v1/membership-applications');
        self::assertResponseIsSuccessful();
        $adminList = $this->responseData();
        self::assertSame(1, $adminList['total']);
        self::assertIsArray($adminList['items']);
        self::assertIsArray($adminList['items'][0]);
        $adminApplication = $adminList['items'][0];
        self::assertSame('pending', $adminApplication['status']);
        self::assertIsString($adminApplication['iban']);
        self::assertStringContainsString('•', $adminApplication['iban']);
        self::assertStringNotContainsString('370400440532013000', $adminApplication['iban']);

        $claim = self::getContainer()->get(ClaimMembershipApplicationsUseCase::class);
        if (!$claim instanceof ClaimMembershipApplicationsUseCase) {
            throw new \LogicException('Der Claim-Use-Case ist im Testcontainer nicht verfügbar.');
        }
        $claimed = $claim->execute(10);
        self::assertCount(1, $claimed);
        self::assertSame('processing', $claimed[0]->status->value);
        self::assertSame('DE89370400440532013000', $claimed[0]->iban);

        $complete = self::getContainer()->get(CompleteMembershipApplicationUseCase::class);
        if (!$complete instanceof CompleteMembershipApplicationUseCase) {
            throw new \LogicException('Der Complete-Use-Case ist im Testcontainer nicht verfügbar.');
        }
        $completed = $complete->execute($applicationId, 'EXT-2026-0001');
        self::assertSame('done', $completed->status->value);
        self::assertSame('EXT-2026-0001', $completed->externalReference);

        $this->client->request('GET', '/api/admin/v1/membership-applications/'.$applicationId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $adminDetail = $this->responseData();
        self::assertSame('done', $adminDetail['status']);
        self::assertSame('EXT-2026-0001', $adminDetail['externalReference']);
    }

    public function testIntegrationApiIsDisabledWithoutConfiguredToken(): void
    {
        $this->client->jsonRequest('POST', '/api/integration/v1/membership-applications/claim', ['limit' => 10]);

        self::assertResponseStatusCodeSame(503);
        $response = $this->responseData();
        self::assertIsArray($response['error']);
        self::assertSame('integration_disabled', $response['error']['code']);
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
