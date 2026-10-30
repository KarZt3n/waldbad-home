<?php

namespace App\Tests\Functional\UI\Content\SocialMedia\Http;

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

final class SocialMediaControllerTest extends WebTestCase
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

    public function testPublisherMaintainsLinksAndVisibilityControlsPublicBar(): void
    {
        $this->client->request('GET', '/api/public/v1/social-media');
        self::assertResponseIsSuccessful();
        self::assertSame(['items' => []], $this->responseData());

        $csrfToken = $this->login('social-publisher@example.test', ModuleRole::Publisher);
        $links = [
            ['platform' => 'instagram', 'url' => 'https://www.instagram.com/waldbad'],
            ['platform' => 'facebook', 'url' => 'https://www.facebook.com/waldbad'],
        ];

        $this->client->jsonRequest('PUT', '/api/admin/v1/social-media', ['visible' => false, 'links' => $links], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $admin = $this->responseData();
        self::assertFalse($admin['visible']);
        self::assertSame([
            ['platform' => 'instagram', 'label' => 'Instagram', 'url' => 'https://www.instagram.com/waldbad'],
            ['platform' => 'facebook', 'label' => 'Facebook', 'url' => 'https://www.facebook.com/waldbad'],
        ], $admin['links']);

        $this->client->request('GET', '/api/public/v1/social-media');
        self::assertSame(['items' => []], $this->responseData());

        $this->client->jsonRequest('PUT', '/api/admin/v1/social-media', ['visible' => true, 'links' => array_reverse($links)], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/public/v1/social-media');
        self::assertSame(['items' => [
            ['platform' => 'facebook', 'label' => 'Facebook', 'url' => 'https://www.facebook.com/waldbad'],
            ['platform' => 'instagram', 'label' => 'Instagram', 'url' => 'https://www.instagram.com/waldbad'],
        ]], $this->responseData());
    }

    public function testRejectsInvalidInput(): void
    {
        $csrfToken = $this->login('social-admin@example.test', ModuleRole::Publisher);

        $this->client->jsonRequest('PUT', '/api/admin/v1/social-media', [
            'visible' => true,
            'links' => [['platform' => 'myspace', 'url' => 'https://myspace.test']],
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(400);

        $this->client->jsonRequest('PUT', '/api/admin/v1/social-media', [
            'visible' => true,
            'links' => [['platform' => 'instagram', 'url' => 'javascript:alert(1)']],
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('PUT', '/api/admin/v1/social-media', [
            'visible' => true,
            'links' => [
                ['platform' => 'instagram', 'url' => 'https://www.instagram.com/a'],
                ['platform' => 'instagram', 'url' => 'https://www.instagram.com/b'],
            ],
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);
    }

    /** Die Leiste ist ohne Freigabe sofort auf allen Seiten online — daher nur mit Veröffentlichungsrecht. */
    public function testEditorWithoutPublishRightCanViewButNotChangeLinks(): void
    {
        $csrfToken = $this->login('social-editor@example.test', ModuleRole::Editor);

        $this->client->request('GET', '/api/admin/v1/social-media');
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('PUT', '/api/admin/v1/social-media', ['visible' => true, 'links' => []], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
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
            displayName: 'Social Media User',
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
