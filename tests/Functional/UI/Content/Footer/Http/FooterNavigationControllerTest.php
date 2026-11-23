<?php

namespace App\Tests\Functional\UI\Content\Footer\Http;

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

final class FooterNavigationControllerTest extends WebTestCase
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

    public function testPublisherDefinesFooterPagesAndOrder(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->login('footer-publisher@example.test', ModuleRole::Publisher)];
        $imprintId = $this->publishedPage('Impressum', 'impressum', $headers);
        $this->publishedPage('Kontakt', 'kontakt', $headers);
        $directionsId = $this->publishedPage('Anfahrt', 'anfahrt', $headers);
        $draftId = $this->page('Entwurf', 'entwurf', $headers);

        // Noch nie gespeichert: bisherige Belegung des Footers.
        $this->client->request('GET', '/api/public/v1/footer-navigation');
        self::assertResponseIsSuccessful();
        self::assertSame(['items' => [
            ['label' => 'Impressum', 'slug' => 'impressum'],
            ['label' => 'Kontakt', 'slug' => 'kontakt'],
        ]], $this->responseData());

        $this->client->jsonRequest('PUT', '/api/admin/v1/footer-navigation', [
            'pageIds' => [$directionsId, $draftId, $imprintId],
        ], $headers);
        self::assertResponseIsSuccessful();
        $admin = $this->responseData();
        self::assertSame([
            ['id' => $directionsId, 'label' => 'Anfahrt', 'slug' => 'anfahrt', 'online' => true],
            ['id' => $draftId, 'label' => 'Entwurf', 'slug' => 'entwurf', 'online' => false],
            ['id' => $imprintId, 'label' => 'Impressum', 'slug' => 'impressum', 'online' => true],
        ], $admin['pages']);
        self::assertIsArray($admin['available']);
        self::assertCount(4, $admin['available']);

        // Nicht veröffentlichte Seiten erscheinen erst nach dem Veröffentlichen; Kontakt ist entfernt.
        $this->client->request('GET', '/api/public/v1/footer-navigation');
        self::assertSame(['items' => [
            ['label' => 'Anfahrt', 'slug' => 'anfahrt'],
            ['label' => 'Impressum', 'slug' => 'impressum'],
        ]], $this->responseData());
    }

    public function testRejectsInvalidInput(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->login('footer-admin@example.test', ModuleRole::Publisher)];
        $imprintId = $this->publishedPage('Impressum', 'impressum', $headers);

        $this->client->jsonRequest('PUT', '/api/admin/v1/footer-navigation', ['pageIds' => [42]], $headers);
        self::assertResponseStatusCodeSame(400);

        $this->client->jsonRequest('PUT', '/api/admin/v1/footer-navigation', ['pageIds' => [$imprintId, $imprintId]], $headers);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('PUT', '/api/admin/v1/footer-navigation', ['pageIds' => ['gibt-es-nicht']], $headers);
        self::assertResponseStatusCodeSame(422);
    }

    /** Der Footer ist ohne Freigabe sofort auf allen Seiten online — daher nur mit Veröffentlichungsrecht. */
    public function testEditorWithoutPublishRightCanViewButNotChangeFooter(): void
    {
        $csrfToken = $this->login('footer-editor@example.test', ModuleRole::Editor);

        $this->client->request('GET', '/api/admin/v1/footer-navigation');
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('PUT', '/api/admin/v1/footer-navigation', ['pageIds' => []], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(403);
    }

    /** @param array<string, string> $headers */
    private function publishedPage(string $title, string $slug, array $headers): string
    {
        $id = $this->page($title, $slug, $headers);
        $this->client->jsonRequest('POST', '/api/admin/v1/pages/'.$id.'/publish', [], $headers);
        self::assertResponseIsSuccessful();

        return $id;
    }

    /** @param array<string, string> $headers */
    private function page(string $title, string $slug, array $headers): string
    {
        $this->client->jsonRequest('POST', '/api/admin/v1/pages', [
            'title' => $title,
            'slug' => $slug,
            'navigationLabel' => $title,
            'parentId' => null,
            'blocks' => [['type' => 'rich_text', 'content' => '<p>Inhalt</p>']],
            'visible' => true,
            'showInNavigation' => false,
            'navigationPosition' => 0,
            'seoTitle' => $title,
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $id = $this->responseData()['id'];
        self::assertIsString($id);

        return $id;
    }

    private function login(string $email, ModuleRole $pagesRole): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: $email,
            displayName: 'Footer User',
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
