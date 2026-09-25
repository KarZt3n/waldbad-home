<?php

namespace App\Tests\Functional\Scenarios\Media;

use App\Logic\IdentityAccess\User\Dto\CreateUserRequest;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\UseCase\CreateUserUseCase;
use App\Tests\Support\FixedSecureTokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PhotoAlbumWorkflowTest extends WebTestCase
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

    public function testEntriesAreManagedAndOnlyVisibleOnesArePublic(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $this->client->jsonRequest('POST', '/api/admin/v1/photo-albums', [
            'title' => '15. Borkheider Wald(bad)lauf am 01.05.2026',
            'date' => '2026-05-01',
            'visible' => true,
            'actions' => [
                ['label' => 'Öffnen', 'url' => 'https://photos.google.com/share/a', 'pageId' => null],
                ['label' => 'Ergebnisse', 'url' => 'https://my.raceresult.com/396583/results', 'pageId' => null],
            ],
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $created = $this->responseData();
        self::assertSame(2026, $created['year']);
        $id = $created['id'];
        self::assertIsString($id);

        $this->client->jsonRequest('POST', '/api/admin/v1/photo-albums', [
            'title' => 'Entwurf',
            'date' => '2025-01-01',
            'visible' => false,
            'actions' => [],
        ], $headers);
        self::assertResponseStatusCodeSame(201);

        $this->client->jsonRequest('POST', '/api/admin/v1/photo-albums', [
            'title' => 'Ungültig',
            'date' => '2025-01-01',
            'actions' => [['label' => 'Öffnen', 'url' => null, 'pageId' => null]],
        ], $headers);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('GET', '/api/public/v1/photo-albums');
        self::assertResponseIsSuccessful();
        $public = $this->responseData();
        self::assertSame(1, $public['total']);
        $items = $public['items'];
        self::assertIsArray($items);
        self::assertIsArray($items[0]);
        self::assertSame(['Öffnen', 'Ergebnisse'], array_column(is_array($items[0]['actions']) ? $items[0]['actions'] : [], 'label'));

        $this->client->jsonRequest('PUT', '/api/admin/v1/photo-albums/'.$id, [
            'title' => '15. Borkheider Wald(bad)lauf am 01.05.2026',
            'date' => '2026-05-01',
            'visible' => true,
            'actions' => [['label' => 'Öffnen', 'url' => 'https://photos.google.com/share/b', 'pageId' => null]],
        ], $headers);
        self::assertResponseIsSuccessful();
        self::assertCount(1, is_array($this->responseData()['actions']) ? $this->responseData()['actions'] : []);

        $this->client->jsonRequest('DELETE', '/api/admin/v1/photo-albums/'.$id, [], $headers);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/api/public/v1/photo-albums');
        self::assertSame(0, $this->responseData()['total']);
    }

    public function testLegacyImportIsRepeatableWithoutDuplicates(): void
    {
        $kernel = $this->client->getKernel();
        $tester = new CommandTester((new Application($kernel))->find('app:photo-albums:import'));
        $file = dirname(__DIR__, 4).'/docs/content-migration/photo-albums.json';

        self::assertSame(0, $tester->execute(['file' => $file]));
        self::assertStringContainsString('162 Foto-Einträge angelegt, 0 bereits vorhanden', $tester->getDisplay());
        self::assertSame(0, $tester->execute(['file' => $file]));
        self::assertStringContainsString('0 Foto-Einträge angelegt, 162 bereits vorhanden', $tester->getDisplay());

        $this->client->request('GET', '/api/public/v1/photo-albums');
        $items = $this->responseData()['items'];
        self::assertIsArray($items);
        self::assertIsArray($items[0]);
        self::assertSame(2026, $items[0]['year']);
        $last = $items[count($items) - 1];
        self::assertIsArray($last);
        self::assertSame('Eröffnung des Waldbades Borkheide am 14. Juni 2003', $last['title']);
    }

    public function testAdminEndpointsRequireAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/v1/photo-albums');

        self::assertContains($this->client->getResponse()->getStatusCode(), [401, 403]);
    }

    private function loginAsAdmin(): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: 'photos-admin@example.test',
            displayName: 'Fotos Admin',
            roles: [Role::SuperAdmin],
            moduleAccess: [new ModuleAccess(CmsModule::Photos, ModuleRole::Editor)],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login-requests', ['email' => 'photos-admin@example.test']);
        $this->client->jsonRequest('POST', '/api/auth/v1/login', ['token' => FixedSecureTokenGenerator::TOKEN]);
        self::assertResponseIsSuccessful();
        $login = $this->responseData();
        self::assertIsString($login['csrfToken']);

        return $login['csrfToken'];
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        $content = $this->client->getResponse()->getContent();
        if (!is_string($content) || $content === '') {
            return [];
        }
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \LogicException('Die Testantwort enthält kein JSON.');
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
