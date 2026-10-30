<?php

namespace App\Tests\Functional\UI\Content\Branding\Http;

use App\Logic\IdentityAccess\User\Dto\CreateUserRequest;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\UseCase\CreateUserUseCase;
use App\Tests\Support\FixedSecureTokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SiteBrandingControllerTest extends WebTestCase
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

    public function testPublisherChangesLogoShownOnPublicPages(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#app[data-logo-url="/downloads/waldbad-borkheide-logo.svg"]');

        $csrfToken = $this->login('logo-publisher@example.test', ModuleRole::Publisher);
        $this->client->jsonRequest('PUT', '/api/admin/v1/site-branding', ['logoUrl' => '/uploads/logos/neu.png'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertSame([
            'logoUrl' => '/uploads/logos/neu.png',
            'customLogoUrl' => '/uploads/logos/neu.png',
            'defaultLogoUrl' => '/downloads/waldbad-borkheide-logo.svg',
        ], $this->responseData());

        $this->client->request('GET', '/seite/kontakt');
        self::assertSelectorExists('#app[data-logo-url="/uploads/logos/neu.png"]');

        $this->client->jsonRequest('PUT', '/api/admin/v1/site-branding', ['logoUrl' => null], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertNull($this->responseData()['customLogoUrl']);
    }

    public function testRejectsInvalidInput(): void
    {
        $csrfToken = $this->login('logo-admin@example.test', ModuleRole::Publisher);

        $this->client->jsonRequest('PUT', '/api/admin/v1/site-branding', ['logoUrl' => 42], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(400);

        $this->client->jsonRequest('PUT', '/api/admin/v1/site-branding', ['logoUrl' => 'https://example.test/logo.png'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);
    }

    /** Das Logo ist ohne Freigabe sofort auf allen Seiten online — daher nur mit Veröffentlichungsrecht. */
    public function testEditorWithoutPublishRightCanViewButNotChangeLogo(): void
    {
        $csrfToken = $this->login('logo-editor@example.test', ModuleRole::Editor);

        $this->client->request('GET', '/api/admin/v1/site-branding');
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('PUT', '/api/admin/v1/site-branding', ['logoUrl' => '/uploads/logo.png'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(403);
    }

    private function login(string $email, ModuleRole $pagesRole): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: $email,
            displayName: 'Logo User',
            roles: [],
            moduleAccess: [new ModuleAccess(CmsModule::Pages, $pagesRole)],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login-requests', ['email' => $email]);
        $this->client->jsonRequest('POST', '/api/auth/v1/login', ['token' => FixedSecureTokenGenerator::TOKEN]);
        self::assertResponseIsSuccessful();
        $csrfToken = $this->responseData()['csrfToken'];
        self::assertIsString($csrfToken);

        return $csrfToken;
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
        $result = [];
        foreach ($data as $key => $value) {
            self::assertIsString($key);
            $result[$key] = $value;
        }

        return $result;
    }
}
