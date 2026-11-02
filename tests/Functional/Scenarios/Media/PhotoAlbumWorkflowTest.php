<?php

namespace App\Tests\Functional\Scenarios\Media;

use App\Logic\IdentityAccess\User\Dto\CreateUserRequest;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\UseCase\CreateUserUseCase;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;
use App\Logic\Media\PhotoAlbum\PhotoAlbumCacheInterface;
use App\Tests\Support\FixedSecureTokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Cache\CacheItemPoolInterface;
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
        $cache = self::getContainer()->get('cache.photo_albums');
        if (!$cache instanceof CacheItemPoolInterface) {
            throw new \LogicException('Der Cache des Fotoarchivs ist im Testcontainer nicht verfügbar.');
        }
        $cache->clear();
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

        $this->client->request('GET', '/api/public/v1/photo-albums/years');
        self::assertResponseIsSuccessful();
        self::assertSame([['year' => 2026, 'count' => 1]], array_map(
            static fn (array $year): array => ['year' => $year['year'], 'count' => $year['count']],
            $this->yearItems(),
        ));
        $items = $this->publicYear(2026)['items'];
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
        $this->client->request('GET', '/api/public/v1/photo-albums/years');
        self::assertSame([], $this->yearItems());
    }

    public function testSavingWarmsUpTheServerSideCache(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $this->client->jsonRequest('POST', '/api/admin/v1/photo-albums', [
            'title' => 'Eisbaden',
            'date' => '2025-01-03',
            'visible' => true,
            'actions' => [],
        ], $headers);
        self::assertResponseStatusCodeSame(201);

        $cached = $this->photoAlbumCache()->load();
        self::assertNotNull($cached);
        self::assertSame(['Eisbaden'], array_map(static fn (PhotoAlbum $album): string => $album->title, $cached));

        // Ohne Warm-up nach dem Speichern käme die Änderung nicht im öffentlichen Archiv an, solange der Cache gilt.
        $this->photoAlbumCache()->store([]);
        $this->client->jsonRequest('POST', '/api/admin/v1/photo-albums', [
            'title' => 'Waldbadlauf',
            'date' => '2025-05-01',
            'visible' => true,
            'actions' => [],
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $items = $this->publicYear(2025)['items'];
        self::assertIsArray($items);
        self::assertSame(['Waldbadlauf', 'Eisbaden'], array_column($items, 'title'));
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

        $this->client->request('GET', '/api/public/v1/photo-albums/years');
        $years = $this->yearItems();
        self::assertSame(2026, $years[0]['year']);
        self::assertSame(162, array_sum(array_column($years, 'count')));
        $items = $this->publicYear(2003)['items'];
        self::assertIsArray($items);
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

    private function photoAlbumCache(): PhotoAlbumCacheInterface
    {
        $cache = self::getContainer()->get(PhotoAlbumCacheInterface::class);
        if (!$cache instanceof PhotoAlbumCacheInterface) {
            throw new \LogicException('Der Cache des Fotoarchivs ist im Testcontainer nicht verfügbar.');
        }

        return $cache;
    }

    /** @return array<string, mixed> */
    private function publicYear(int $year): array
    {
        $this->client->request('GET', '/api/public/v1/photo-albums/years/'.$year);
        self::assertResponseIsSuccessful();

        return $this->responseData();
    }

    /** @return list<array<array-key, mixed>> */
    private function yearItems(): array
    {
        $items = $this->responseData()['items'];
        self::assertIsArray($items);

        return array_values(array_filter($items, is_array(...)));
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
