<?php

namespace App\Tests\UI;

use App\Logic\Content\Extension\Model\PageExtension;
use App\Logic\Content\Extension\Model\PageExtensionTexts;
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

final class PageExtensionWorkflowTest extends WebTestCase
{
    private const string PRIVACY_TEXT = 'Ich stimme der Verarbeitung meiner Daten gemäß der neuen Datenschutzerklärung zu.';

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

    /**
     * Ein geänderter Bestätigungstext ändert die Version: ein Formular mit dem alten Wortlaut wird
     * abgewiesen, der Antrag mit dem neuen Wortlaut speichert genau diesen Text.
     */
    public function testChangedConsentTextIsShownPubliclyAndStoredWithTheApplication(): void
    {
        $defaultVersion = (new PageExtensionTexts(PageExtension::MembershipApplication))->consentVersion();
        $csrfToken = $this->login('extension-publisher@example.test', [], ModuleRole::Publisher);

        $this->client->request('GET', '/api/admin/v1/page-extensions');
        self::assertResponseIsSuccessful();
        $membershipExtension = $this->extension($this->responseData(), 'membership_application');
        self::assertContains('consentPrivacy', array_column($this->list($membershipExtension, 'fields'), 'key'));

        $this->client->jsonRequest('PUT', '/api/admin/v1/page-extensions/membership_application', [
            'texts' => ['consentPrivacy' => '  '.self::PRIVACY_TEXT.'  '],
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $privacyField = $this->field($this->extension($this->responseData(), 'membership_application'), 'consentPrivacy');
        self::assertSame(self::PRIVACY_TEXT, $privacyField['text']);
        self::assertFalse($privacyField['isDefault']);

        $this->client->request('GET', '/api/public/v1/page-extensions/membership_application/texts');
        self::assertResponseIsSuccessful();
        $public = $this->responseData();
        self::assertSame(self::PRIVACY_TEXT, $this->array($public, 'texts')['consentPrivacy']);
        self::assertIsString($public['consentVersion']);
        self::assertNotSame($defaultVersion, $public['consentVersion']);

        $this->client->jsonRequest('POST', '/api/public/v1/membership-applications', $this->application($defaultVersion));
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('POST', '/api/public/v1/membership-applications', $this->application($public['consentVersion']));
        self::assertResponseStatusCodeSame(202);

        $this->client->request('GET', '/api/admin/v1/membership-applications');
        self::assertResponseIsSuccessful();
        $application = $this->list($this->responseData(), 'items')[0];
        self::assertSame($public['consentVersion'], $application['declarationVersion']);
        self::assertSame([
            'Ich erkenne die Vereinssatzung und die Beitragsordnung an.',
            self::PRIVACY_TEXT,
            'Ich ermächtige den Naturbad Borkheide e.V. widerruflich, die Mitgliedsbeiträge per Lastschrift einzuziehen.',
        ], $application['acceptedDeclarations']);

        $this->client->jsonRequest('POST', '/api/admin/v1/page-extensions/membership_application/reset', [], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/public/v1/page-extensions/membership_application/texts');
        self::assertSame($defaultVersion, $this->responseData()['consentVersion']);
    }

    public function testRejectsEmptyAndUnknownTextFields(): void
    {
        $csrfToken = $this->login('extension-admin@example.test', [Role::Admin], ModuleRole::Editor);

        $this->client->jsonRequest('PUT', '/api/admin/v1/page-extensions/membership_application', [
            'texts' => ['title' => '   '],
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('PUT', '/api/admin/v1/page-extensions/membership_application', [
            'texts' => ['unknown' => 'Text'],
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('PUT', '/api/admin/v1/page-extensions/sauna', [
            'texts' => ['title' => 'Sauna'],
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('GET', '/api/public/v1/page-extensions/unknown/texts');
        self::assertResponseStatusCodeSame(404);
    }

    /** Die Texte sind ohne Freigabe sofort auf allen Seiten online — daher nur mit Veröffentlichungsrecht. */
    public function testEditorWithoutPublishRightCanViewButNotChangeTexts(): void
    {
        $csrfToken = $this->login('extension-editor@example.test', [], ModuleRole::Editor);

        $this->client->request('GET', '/api/admin/v1/page-extensions');
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('PUT', '/api/admin/v1/page-extensions/membership_application', [
            'texts' => ['title' => 'Neue Überschrift'],
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(403);

        $this->client->jsonRequest('POST', '/api/admin/v1/page-extensions/membership_application/reset', [], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function application(?string $declarationVersion): array
    {
        return [
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
            'emailConsent' => false,
            'declarationVersion' => $declarationVersion,
        ];
    }

    /**
     * @param list<Role> $roles
     */
    private function login(string $email, array $roles, ModuleRole $pagesRole): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: $email,
            displayName: 'Extension User',
            roles: $roles,
            moduleAccess: [
                new ModuleAccess(CmsModule::Pages, $pagesRole),
                new ModuleAccess(CmsModule::MembershipApplications, ModuleRole::Editor),
            ],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login-requests', ['email' => $email]);
        $this->client->jsonRequest('POST', '/api/auth/v1/login', ['token' => FixedSecureTokenGenerator::TOKEN]);
        self::assertResponseIsSuccessful();
        $csrfToken = $this->responseData()['csrfToken'];
        self::assertIsString($csrfToken);

        return $csrfToken;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function extension(array $data, string $key): array
    {
        foreach ($this->list($data, 'items') as $extension) {
            if ($extension['key'] === $key) {
                return $extension;
            }
        }
        self::fail(sprintf('Die Erweiterung "%s" fehlt in der Liste.', $key));
    }

    /**
     * @param array<string, mixed> $extension
     * @return array<string, mixed>
     */
    private function field(array $extension, string $key): array
    {
        foreach ($this->list($extension, 'fields') as $field) {
            if ($field['key'] === $key) {
                return $field;
            }
        }
        self::fail(sprintf('Das Textfeld "%s" fehlt.', $key));
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function list(array $data, string $key): array
    {
        $items = $data[$key] ?? null;
        self::assertIsArray($items);
        $list = [];
        foreach ($items as $item) {
            self::assertIsArray($item);
            $list[] = $this->stringKeys($item);
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        self::assertIsArray($value);

        return $this->stringKeys($value);
    }

    /**
     * @param array<mixed> $data
     * @return array<string, mixed>
     */
    private function stringKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            self::assertIsString($key);
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $this->stringKeys($data);
    }
}
