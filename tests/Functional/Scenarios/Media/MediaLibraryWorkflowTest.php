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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaLibraryWorkflowTest extends WebTestCase
{
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private KernelBrowser $client;

    private const string TEST_FOLDER = 'mediathek-test';

    /** @var list<string> */
    private array $storedFiles = [];

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

    protected function tearDown(): void
    {
        foreach ($this->storedFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->removeDirectory(dirname(__DIR__, 4).'/public/uploads/'.self::TEST_FOLDER);
        parent::tearDown();
    }

    public function testImagesAreReplacedAndDeletedTogetherWithTheirReferences(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];

        $originalUrl = $this->upload('/api/admin/v1/media-library', 'mediathek-test-original.png', $headers, ['folder' => 'media', 'source' => 'Foto: Verein'])['url'];
        self::assertIsString($originalUrl);

        $this->client->jsonRequest('POST', '/api/admin/v1/pages', [
            'title' => 'Mediathek-Test',
            'slug' => 'mediathek-test',
            'navigationLabel' => 'Mediathek-Test',
            'parentId' => null,
            'blocks' => [
                ['type' => 'image', 'content' => '', 'mediaUrl' => $originalUrl, 'mediaAlt' => 'Badeteich'],
                [
                    'type' => 'image_text',
                    'content' => '<p>Text neben dem Bild.</p>',
                    'mediaUrl' => $originalUrl,
                    'mediaAlt' => 'Badeteich',
                    'layout' => 'image_left',
                ],
                ['type' => 'rich_text', 'content' => '<p>Weiterer Inhalt</p>'],
            ],
            'visible' => true,
            'showInNavigation' => false,
            'navigationPosition' => 0,
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->responseData()['id'];
        self::assertIsString($pageId);
        $this->client->jsonRequest('POST', '/api/admin/v1/pages/'.$pageId.'/publish', [], $headers);
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('POST', '/api/admin/v1/events', [
            'kind' => 'event',
            'title' => 'Sommerfest',
            'date' => '2026-07-04',
            'time' => '14:00',
            'content' => '<p>Feiern am Teich.</p>',
            'mediaUrl' => $originalUrl,
            'mediaAlt' => 'Badeteich',
            'helpEnabled' => false,
            'visible' => true,
            'activities' => [],
            'callToActions' => [],
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $eventId = $this->responseData()['id'];
        self::assertIsString($eventId);

        $original = $this->libraryItem($originalUrl);
        $usages = $this->list($original['usages']);
        self::assertCount(2, $usages);
        self::assertSame(['page', 'event_schedule'], array_column($usages, 'area'));
        self::assertSame('Entwurf und veröffentlichte Fassung', $usages[0]['detail']);
        self::assertSame('Veranstaltung am 04.07.2026', $usages[1]['detail']);

        $replacement = $this->upload('/api/admin/v1/media-library/replace', 'mediathek-test-neu.png', $headers, ['url' => $originalUrl]);
        self::assertSame(2, $replacement['updatedReferences']);
        $replacementImage = $this->map($replacement['file']);
        $replacementUrl = $replacementImage['url'];
        self::assertIsString($replacementUrl);
        self::assertNotSame($originalUrl, $replacementUrl);
        self::assertSame('Foto: Verein', $replacementImage['source']);
        self::assertFileDoesNotExist($this->publicPath($originalUrl));
        self::assertNull($this->findLibraryItem($originalUrl));
        self::assertCount(2, $this->list($this->libraryItem($replacementUrl)['usages']));

        $this->client->request('GET', '/api/public/v1/pages/mediathek-test');
        self::assertResponseIsSuccessful();
        $publishedBlocks = $this->list($this->responseData()['blocks']);
        self::assertSame([$replacementUrl, $replacementUrl, null], array_column($publishedBlocks, 'mediaUrl'));
        self::assertSame('Badeteich', $publishedBlocks[0]['mediaAlt']);
        self::assertSame($replacementUrl, $this->event($eventId)['mediaUrl']);

        $this->client->jsonRequest('DELETE', '/api/admin/v1/media-library', ['url' => $replacementUrl], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->responseData()['removedReferences']);
        self::assertFileDoesNotExist($this->publicPath($replacementUrl));
        self::assertNull($this->findLibraryItem($replacementUrl));

        $this->client->request('GET', '/api/public/v1/pages/mediathek-test');
        $publishedBlocks = $this->list($this->responseData()['blocks']);
        self::assertSame(['rich_text', 'rich_text'], array_column($publishedBlocks, 'type'));
        self::assertSame('<p>Text neben dem Bild.</p>', $publishedBlocks[0]['content']);
        $event = $this->event($eventId);
        self::assertNull($event['mediaUrl']);
        self::assertNull($event['mediaAlt']);

        $this->client->jsonRequest('DELETE', '/api/admin/v1/media-library', ['url' => $replacementUrl], $headers);
        self::assertResponseStatusCodeSame(404);
    }

    public function testUnusedImageIsListedWithoutUsagesAndInvalidUrlsAreRejected(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $url = $this->upload('/api/admin/v1/media-library', 'mediathek-test-unbenutzt.png', $headers, ['folder' => 'media'])['url'];
        self::assertIsString($url);

        self::assertSame([], $this->libraryItem($url)['usages']);

        $this->client->jsonRequest('DELETE', '/api/admin/v1/media-library', ['url' => '/etc/passwd'], $headers);
        self::assertResponseStatusCodeSame(400);

        $this->client->jsonRequest('DELETE', '/api/admin/v1/media-library', ['url' => $url], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->responseData()['removedReferences']);
    }

    public function testFoldersStructureDocumentsAndMovesUpdateReferences(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];

        $this->client->jsonRequest('POST', '/api/admin/v1/media-library/folders', ['parentPath' => '', 'name' => self::TEST_FOLDER], $headers);
        self::assertResponseStatusCodeSame(201);
        $this->client->jsonRequest('POST', '/api/admin/v1/media-library/folders', ['parentPath' => self::TEST_FOLDER, 'name' => 'dokumente'], $headers);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(self::TEST_FOLDER.'/dokumente', $this->responseData()['path']);
        $this->client->jsonRequest('POST', '/api/admin/v1/media-library/folders', ['parentPath' => self::TEST_FOLDER, 'name' => 'Ungültig Name'], $headers);
        self::assertResponseStatusCodeSame(422);
        $this->client->jsonRequest('POST', '/api/admin/v1/media-library/folders', ['parentPath' => '../etc', 'name' => 'x'], $headers);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/api/admin/v1/media-library/folders');
        $paths = array_column($this->list($this->responseData()['items']), 'path');
        self::assertContains('media', $paths);
        self::assertContains(self::TEST_FOLDER.'/dokumente', $paths);

        $pdf = $this->upload('/api/admin/v1/media-library', 'Satzung 2026.pdf', $headers, ['folder' => self::TEST_FOLDER.'/dokumente'], self::pdf());
        self::assertSame('/uploads/'.self::TEST_FOLDER.'/dokumente/satzung-2026.pdf', $pdf['url']);
        self::assertSame('application/pdf', $pdf['mimeType']);
        self::assertFalse($pdf['isImage']);
        self::assertNull($pdf['width']);
        $image = $this->upload('/api/admin/v1/media-library', 'teich.png', $headers, ['folder' => self::TEST_FOLDER]);
        $imageUrl = $image['url'];
        self::assertIsString($imageUrl);

        $this->client->request('GET', '/api/admin/v1/media-library?folder='.self::TEST_FOLDER);
        self::assertSame([$imageUrl], array_column($this->list($this->responseData()['items']), 'url'));
        $this->client->request('GET', '/api/admin/v1/media-library?folder='.self::TEST_FOLDER.'&recursive=1');
        self::assertCount(2, $this->list($this->responseData()['items']));

        $this->client->jsonRequest('DELETE', '/api/admin/v1/media-library/folders', ['path' => self::TEST_FOLDER.'/dokumente'], $headers);
        self::assertResponseStatusCodeSame(422);

        $replaced = $this->upload('/api/admin/v1/media-library/replace', 'neue-satzung.pdf', $headers, ['url' => $pdf['url']], self::pdf());
        self::assertSame($pdf['url'], $this->map($replaced['file'])['url']);
        $this->upload('/api/admin/v1/media-library/replace', 'bild.png', $headers, ['url' => $pdf['url']], null, 422);

        $this->client->jsonRequest('POST', '/api/admin/v1/events', [
            'kind' => 'event',
            'title' => 'Teichfest',
            'date' => '2026-08-01',
            'time' => '15:00',
            'content' => '<p>Am Teich.</p>',
            'mediaUrl' => $imageUrl,
            'mediaAlt' => 'Teich',
            'helpEnabled' => false,
            'visible' => true,
            'activities' => [],
            'callToActions' => [],
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $eventId = $this->responseData()['id'];
        self::assertIsString($eventId);

        $this->client->jsonRequest('POST', '/api/admin/v1/media-library/move', ['url' => $imageUrl, 'targetFolder' => self::TEST_FOLDER.'/dokumente'], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['updatedReferences']);
        $movedUrl = $this->map($this->responseData()['file'])['url'];
        self::assertSame('/uploads/'.self::TEST_FOLDER.'/dokumente/teich.png', $movedUrl);
        self::assertFileDoesNotExist($this->publicPath($imageUrl));
        self::assertFileExists($this->publicPath($movedUrl));
        self::assertSame($movedUrl, $this->event($eventId)['mediaUrl']);
        self::assertSame('Teich', $this->event($eventId)['mediaAlt']);
        self::assertCount(1, $this->list($this->libraryItem($movedUrl)['usages']));

        $this->client->request('GET', '/api/admin/v1/media/images');
        self::assertStringContainsString(str_replace('/', '\/', $movedUrl), (string) $this->client->getResponse()->getContent());

        $this->client->jsonRequest('POST', '/api/admin/v1/media-library/rename', ['url' => $movedUrl, 'filename' => 'Großer Teich'], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['updatedReferences']);
        $renamed = $this->map($this->responseData()['file']);
        $renamedUrl = $renamed['url'];
        self::assertSame('/uploads/'.self::TEST_FOLDER.'/dokumente/grosser-teich.png', $renamedUrl);
        self::assertSame('grosser-teich.png', $renamed['originalName']);
        self::assertFileDoesNotExist($this->publicPath($movedUrl));
        self::assertFileExists($this->publicPath($renamedUrl));
        self::assertSame($renamedUrl, $this->event($eventId)['mediaUrl']);

        $this->client->jsonRequest('POST', '/api/admin/v1/media-library/rename', ['url' => $renamedUrl, 'filename' => 'grosser-teich'], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->responseData()['updatedReferences']);
        $other = $this->upload('/api/admin/v1/media-library', 'zweiter.png', $headers, ['folder' => self::TEST_FOLDER.'/dokumente']);
        $this->client->jsonRequest('POST', '/api/admin/v1/media-library/rename', ['url' => $other['url'], 'filename' => 'Großer Teich'], $headers);
        self::assertResponseStatusCodeSame(422);
        $this->client->jsonRequest('POST', '/api/admin/v1/media-library/rename', ['url' => $other['url'], 'filename' => '  '], $headers);
        self::assertResponseStatusCodeSame(422);
        $this->client->jsonRequest('DELETE', '/api/admin/v1/media-library', ['url' => $other['url']], $headers);
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('DELETE', '/api/admin/v1/media-library', ['url' => $renamedUrl], $headers);
        self::assertResponseIsSuccessful();
        $this->client->jsonRequest('DELETE', '/api/admin/v1/media-library', ['url' => $pdf['url']], $headers);
        self::assertResponseIsSuccessful();
        $this->client->jsonRequest('DELETE', '/api/admin/v1/media-library/folders', ['path' => self::TEST_FOLDER.'/dokumente'], $headers);
        self::assertResponseStatusCodeSame(204);
        $this->client->jsonRequest('DELETE', '/api/admin/v1/media-library/folders', ['path' => 'media'], $headers);
        self::assertResponseStatusCodeSame(422);
    }

    public function testCallToActionLinkingAFileFollowsRenameAndDeletion(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $this->client->jsonRequest('POST', '/api/admin/v1/media-library/folders', ['parentPath' => '', 'name' => self::TEST_FOLDER], $headers);
        self::assertResponseStatusCodeSame(201);
        $pdfUrl = $this->upload('/api/admin/v1/media-library', 'Badeordnung.pdf', $headers, ['folder' => self::TEST_FOLDER], self::pdf())['url'];
        self::assertIsString($pdfUrl);

        $this->client->jsonRequest('POST', '/api/admin/v1/pages', [
            'title' => 'Mediathek-Handlungsaufruf',
            'slug' => 'mediathek-handlungsaufruf',
            'navigationLabel' => 'Mediathek-Handlungsaufruf',
            'parentId' => null,
            'blocks' => [
                ['type' => 'call_to_action', 'content' => '<p>Alle Regeln im Überblick.</p>', 'linkUrl' => $pdfUrl, 'linkLabel' => 'Badeordnung öffnen'],
            ],
            'visible' => true,
            'showInNavigation' => false,
            'navigationPosition' => 0,
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->responseData()['id'];
        self::assertIsString($pageId);
        $this->client->jsonRequest('POST', '/api/admin/v1/pages/'.$pageId.'/publish', [], $headers);
        self::assertResponseIsSuccessful();

        $usages = $this->list($this->libraryItem($pdfUrl)['usages']);
        self::assertCount(1, $usages);
        self::assertSame('page', $usages[0]['area']);

        $this->client->jsonRequest('POST', '/api/admin/v1/media-library/rename', ['url' => $pdfUrl, 'filename' => 'Badeordnung 2026'], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['updatedReferences']);
        $renamedUrl = $this->map($this->responseData()['file'])['url'];
        self::assertSame('/uploads/'.self::TEST_FOLDER.'/badeordnung-2026.pdf', $renamedUrl);
        $this->client->request('GET', '/api/public/v1/pages/mediathek-handlungsaufruf');
        $block = $this->map($this->list($this->responseData()['blocks'])[0]);
        self::assertSame('call_to_action', $block['type']);
        self::assertSame($renamedUrl, $block['linkUrl']);
        self::assertSame('Badeordnung öffnen', $block['linkLabel']);

        $this->client->jsonRequest('DELETE', '/api/admin/v1/media-library', ['url' => $renamedUrl], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['removedReferences']);
        $this->client->request('GET', '/api/public/v1/pages/mediathek-handlungsaufruf');
        $block = $this->map($this->list($this->responseData()['blocks'])[0]);
        self::assertSame('rich_text', $block['type']);
        self::assertSame('<p>Alle Regeln im Überblick.</p>', $block['content']);
        self::assertNull($block['linkUrl']);
    }

    public function testAdminEndpointsRequireAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/v1/media-library');

        self::assertContains($this->client->getResponse()->getStatusCode(), [401, 403]);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $parameters
     * @return array<string, mixed>
     */
    private function upload(string $uri, string $filename, array $headers, array $parameters = [], ?string $contents = null, ?int $expectedStatus = null): array
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'waldbad-mediathek-');
        $contents ??= base64_decode(self::PNG, true);
        if ($temporaryFile === false || $contents === false || file_put_contents($temporaryFile, $contents) === false) {
            throw new \RuntimeException('Die Testdatei konnte nicht erzeugt werden.');
        }
        $this->client->request(
            method: 'POST',
            uri: $uri,
            parameters: $parameters,
            files: ['file' => new UploadedFile($temporaryFile, $filename, null, null, true)],
            server: $headers,
        );
        if ($expectedStatus !== null) {
            self::assertResponseStatusCodeSame($expectedStatus);

            return [];
        }
        self::assertContains($this->client->getResponse()->getStatusCode(), [200, 201]);
        $data = $this->responseData();
        $url = isset($data['file']) ? $this->map($data['file'])['url'] : $data['url'];
        self::assertIsString($url);
        $this->storedFiles[] = $this->publicPath($url);

        return $data;
    }

    private static function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $entries = scandir($directory);
        foreach ($entries === false ? [] : array_diff($entries, ['.', '..']) as $entry) {
            $path = $directory.'/'.$entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }

    /** @return array<string, mixed> */
    private function libraryItem(string $url): array
    {
        return $this->findLibraryItem($url) ?? throw new \LogicException(sprintf('"%s" fehlt in der Mediathek.', $url));
    }

    /** @return array<string, mixed>|null */
    private function findLibraryItem(string $url): ?array
    {
        $this->client->request('GET', '/api/admin/v1/media-library?recursive=1');
        self::assertResponseIsSuccessful();
        foreach ($this->list($this->responseData()['items']) as $item) {
            if ($item['url'] === $url) {
                return $item;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function event(string $id): array
    {
        $this->client->request('GET', '/api/admin/v1/events');
        self::assertResponseIsSuccessful();
        foreach ($this->list($this->responseData()['items']) as $event) {
            if ($event['id'] === $id) {
                return $event;
            }
        }

        throw new \LogicException(sprintf('Die Veranstaltung "%s" fehlt.', $id));
    }

    private function publicPath(string $url): string
    {
        return dirname(__DIR__, 4).'/public'.$url;
    }

    private function loginAsAdmin(): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: 'media-admin@example.test',
            displayName: 'Mediathek Admin',
            roles: [Role::SuperAdmin],
            moduleAccess: [new ModuleAccess(CmsModule::MediaLibrary, ModuleRole::Editor)],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login-requests', ['email' => 'media-admin@example.test']);
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

        return $this->map(json_decode($content, true, 512, JSON_THROW_ON_ERROR));
    }

    /** @return list<array<string, mixed>> */
    private function list(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \LogicException('Die Testantwort enthält keine Liste.');
        }

        return array_map($this->map(...), $value);
    }

    /** @return array<string, mixed> */
    private function map(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \LogicException('Die Testantwort enthält kein Objekt.');
        }
        $map = [];
        foreach ($value as $key => $entry) {
            if (!is_string($key)) {
                throw new \LogicException('Die Testantwort enthält einen ungültigen Schlüssel.');
            }
            $map[$key] = $entry;
        }

        return $map;
    }
}
