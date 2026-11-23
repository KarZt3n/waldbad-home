<?php

namespace App\Tests\Functional\UI\Site\Http;

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

/** robots.txt, sitemap.xml und die serverseitig ausgelieferten Metadaten öffentlicher Seiten. */
final class PublicSiteSeoTest extends WebTestCase
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

    public function testRobotsAllowPublicContentAndPointToSitemap(): void
    {
        $this->client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString("Disallow: /admin\n", $content);
        self::assertStringContainsString("Disallow: /api/admin/\n", $content);
        self::assertStringNotContainsString('Disallow: /api/public', $content);
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $content);
    }

    public function testSitemapListsOnlyPublishedVisiblePagesAndPagesRenderTheirMetadata(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->login()];
        $this->page('Startseite', 'startseite', $headers, publish: true);
        $this->page('Anfahrt', 'anfahrt', $headers, publish: true, seoTitle: 'Anfahrt & Parken', seoDescription: 'So kommst du zum Waldbad.');
        $this->page('Intern', 'intern', $headers, publish: true, visible: false);
        $this->page('Entwurf', 'entwurf', $headers, publish: false);

        $this->client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        $sitemap = new \SimpleXMLElement((string) $this->client->getResponse()->getContent());
        $locations = [];
        foreach ($sitemap->url as $url) {
            $locations[] = (string) $url->loc;
            self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, (string) $url->lastmod));
        }
        self::assertSame(['http://localhost/', 'http://localhost/seite/anfahrt'], $locations);

        $crawler = $this->client->request('GET', '/seite/anfahrt');
        self::assertResponseIsSuccessful();
        self::assertSame('Anfahrt & Parken – Waldbad Borkheide', $crawler->filter('title')->text());
        self::assertSame('So kommst du zum Waldbad.', $crawler->filter('meta[name="description"]')->attr('content'));
        self::assertSame('http://localhost/seite/anfahrt', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame('Anfahrt & Parken', $crawler->filter('meta[property="og:title"]')->attr('content'));
        self::assertCount(0, $crawler->filter('meta[name="robots"]'));

        // Seite, Navigation, Footer und Social Media kommen mit dem HTML, ohne weitere API-Anfragen.
        $embedded = json_decode($crawler->filter('#site-chrome-data')->text(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($embedded);
        self::assertIsArray($embedded['page']);
        self::assertSame('anfahrt', $embedded['page']['slug']);
        self::assertSame([], $embedded['footerLinks']);
        self::assertSame([], $embedded['socialMediaLinks']);
        self::assertIsArray($embedded['navigation']);

        $crawler = $this->client->request('GET', '/seite/startseite');
        self::assertSame('http://localhost/', $crawler->filter('link[rel="canonical"]')->attr('href'));

        foreach (['/seite/intern', '/seite/entwurf', '/seite/gibt-es-nicht'] as $path) {
            $crawler = $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(404, $path);
            self::assertSame('noindex', $crawler->filter('meta[name="robots"]')->attr('content'));
            self::assertCount(0, $crawler->filter('link[rel="canonical"]'));
        }
    }

    public function testPersonalSpecialPagesAreNotIndexed(): void
    {
        foreach (['/meine-mitgliedschaft', '/e-mail-einwilligung', '/sauna-anfrage'] as $path) {
            $crawler = $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSame('noindex', $crawler->filter('meta[name="robots"]')->attr('content'));
        }
    }

    /** Formulare, Sonderseiten und Erweiterungen wie die Sauna werden erst bei Bedarf nachgeladen. */
    public function testPublicPagePreloadsOnlyCommonModules(): void
    {
        $crawler = $this->client->request('GET', '/meine-mitgliedschaft');

        $preloaded = $crawler->filter('link[rel="modulepreload"]')->each(static fn ($link): string => (string) $link->attr('href'));
        $preloaded = implode("\n", $preloaded);
        self::assertStringContainsString('content-rendering', $preloaded);
        self::assertStringContainsString('site-chrome', $preloaded);
        foreach (['sauna', 'photo-albums', 'membership-application', 'event-help', 'contact', 'guestbook', 'member-self-service', 'email-consent', '/admin'] as $module) {
            self::assertStringNotContainsString($module, $preloaded, $module);
        }
    }

    /** @param array<string, string> $headers */
    private function page(string $title, string $slug, array $headers, bool $publish, bool $visible = true, ?string $seoTitle = null, ?string $seoDescription = null): void
    {
        $this->client->jsonRequest('POST', '/api/admin/v1/pages', [
            'title' => $title,
            'slug' => $slug,
            'navigationLabel' => $title,
            'parentId' => null,
            'blocks' => [['type' => 'rich_text', 'content' => '<p>Inhalt</p>']],
            'visible' => $visible,
            'showInNavigation' => false,
            'navigationPosition' => 0,
            'seoTitle' => $seoTitle ?? $title,
            'seoDescription' => $seoDescription,
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $content = (string) $this->client->getResponse()->getContent();
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsString($data['id']);
        if ($publish) {
            $this->client->jsonRequest('POST', '/api/admin/v1/pages/'.$data['id'].'/publish', [], $headers);
            self::assertResponseIsSuccessful();
        }
    }

    private function login(): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: 'seo-publisher@example.test',
            displayName: 'SEO Publisher',
            roles: [],
            moduleAccess: [new ModuleAccess(CmsModule::Pages, ModuleRole::Publisher)],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login-requests', ['email' => 'seo-publisher@example.test']);
        $this->client->jsonRequest('POST', '/api/auth/v1/login', ['token' => FixedSecureTokenGenerator::TOKEN]);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsString($data['csrfToken']);

        return $data['csrfToken'];
    }
}
