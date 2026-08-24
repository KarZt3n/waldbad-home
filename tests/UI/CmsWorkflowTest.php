<?php

namespace App\Tests\UI;

use App\Logic\Content\Site\UseCase\InitializeSiteUseCase;
use App\Logic\IdentityAccess\User\Dto\CreateUserRequest;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\UseCase\CreateUserUseCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use App\UI\Guestbook\Entry\Cli\ImportPublishedGuestbookEntriesCommand;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CmsWorkflowTest extends WebTestCase
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

    public function testPublishedStarterPageIsAvailableThroughPublicApi(): void
    {
        $initialize = self::getContainer()->get(InitializeSiteUseCase::class);
        if (!$initialize instanceof InitializeSiteUseCase) {
            throw new \LogicException('Die Site-Initialisierung ist im Testcontainer nicht verfügbar.');
        }
        $initialize->execute();

        $this->client->request('GET', '/api/public/v1/pages/startseite');

        self::assertResponseIsSuccessful();
        $page = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($page);
        self::assertSame('startseite', $page['slug']);
        self::assertSame('published', $page['status']);

        $this->client->request('GET', '/api/public/v1/pages/impressum');
        self::assertResponseIsSuccessful();
        $imprint = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($imprint);
        self::assertSame('Impressum', $imprint['title']);
        self::assertFalse($imprint['showInNavigation']);

        $this->client->request('GET', '/api/public/v1/navigation');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('"slug":"impressum"', $this->responseContent());

        $this->client->request('GET', '/api/public/v1/pages/unterstuetzer');
        self::assertResponseIsSuccessful();
        $supporters = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($supporters);
        self::assertSame('Unterstützer', $supporters['title']);
        self::assertIsArray($supporters['blocks']);
        self::assertCount(6, $supporters['blocks']);
        self::assertIsArray($supporters['blocks'][2]);
        self::assertSame('/uploads/media/supporter-dlrg-borkheide.png', $supporters['blocks'][2]['mediaUrl']);
        self::assertIsArray($supporters['blocks'][5]);
        self::assertSame('https://www.hdsports.de/', $supporters['blocks'][5]['linkUrl']);
    }

    public function testPublicMessagesRemainPrivateUntilModerated(): void
    {
        $this->client->jsonRequest('POST', '/api/public/v1/guestbook-entries', [
            'displayName' => 'Badegast',
            'email' => 'gast@example.test',
            'message' => 'Ein wunderschöner Badetag.',
        ]);
        self::assertResponseStatusCodeSame(202);

        $this->client->request('GET', '/api/public/v1/guestbook-entries');
        self::assertResponseIsSuccessful();
        $entries = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($entries);
        self::assertSame([], $entries['items']);

        $this->client->jsonRequest('POST', '/api/public/v1/contact-requests', [
            'name' => 'Erika Musterfrau',
            'email' => 'erika@example.test',
            'subject' => 'Öffnungszeit',
            'message' => 'Wann beginnt die Saison?',
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(202);
    }

    public function testPublishedGuestbookImportIsImmediatelyVisibleAndIdempotent(): void
    {
        $importCommand = self::getContainer()->get(ImportPublishedGuestbookEntriesCommand::class);
        if (!$importCommand instanceof ImportPublishedGuestbookEntriesCommand) {
            throw new \LogicException('Der Gästebuchimport ist im Testcontainer nicht verfügbar.');
        }

        $file = tempnam(sys_get_temp_dir(), 'guestbook-import-');
        if ($file === false) {
            throw new \RuntimeException('Die temporäre Importdatei konnte nicht angelegt werden.');
        }
        $importEntries = array_map(
            static fn (int $number): array => [
                'displayName' => 'Webmaster '.$number,
                'date' => sprintf('%02d.05.2004', $number),
                'time' => '20:20',
                'message' => 'Willkommen im Gästebuch '.$number.'.',
            ],
            range(1, 11),
        );
        file_put_contents($file, json_encode($importEntries, JSON_THROW_ON_ERROR));

        try {
            $command = new CommandTester($importCommand);
            self::assertSame(0, $command->execute(['file' => $file]));
            self::assertStringContainsString('11 Einträge importiert', $command->getDisplay());
            self::assertSame(0, $command->execute(['file' => $file]));
            self::assertStringContainsString('0 Einträge importiert, 11 bereits vorhanden', $command->getDisplay());
        } finally {
            unlink($file);
        }

        $this->client->request('GET', '/api/public/v1/guestbook-entries?limit=10');

        self::assertResponseIsSuccessful();
        $entries = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($entries);
        self::assertIsArray($entries['items']);
        self::assertCount(10, $entries['items']);
        self::assertSame(11, $entries['total']);
        $entry = $entries['items'][0];
        self::assertIsArray($entry);
        self::assertSame('Webmaster 11', $entry['displayName']);
        self::assertSame('Willkommen im Gästebuch 11.', $entry['message']);
        self::assertSame('2004-05-11T20:20:00+02:00', $entry['submittedAt']);

        $this->client->request('GET', '/api/public/v1/guestbook-entries?limit=10&offset=10');
        self::assertResponseIsSuccessful();
        $secondPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($secondPage);
        self::assertIsArray($secondPage['items']);
        self::assertCount(1, $secondPage['items']);
        self::assertSame(11, $secondPage['total']);
    }

    public function testAdminMutationRequiresValidSessionCsrfToken(): void
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: 'admin@example.test',
            displayName: 'Admin',
            plainPassword: 'Ein-sicheres-Testpasswort-2026',
            roles: [Role::SuperAdmin],
        ));

        $this->client->jsonRequest('POST', '/api/auth/v1/login', [
            'email' => 'admin@example.test',
            'password' => 'Ein-sicheres-Testpasswort-2026',
        ]);
        self::assertResponseIsSuccessful();
        $login = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($login);
        self::assertArrayHasKey('csrfToken', $login);
        self::assertIsString($login['csrfToken']);

        $this->client->jsonRequest('POST', '/api/admin/v1/event-activities', [
            'name' => 'Aufbau',
            'description' => 'Tische und Bänke aufstellen.',
            'active' => true,
        ], ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseStatusCodeSame(201);
        $createdActivity = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($createdActivity);
        self::assertIsString($createdActivity['id']);

        $page = [
            'title' => 'Testseite',
            'slug' => 'testseite',
            'navigationLabel' => 'Test',
            'parentId' => null,
            'blocks' => [
                [
                    'type' => 'rich_text',
                    'content' => '<p><strong>Fett</strong> und <font color="#ff0000" size="4">farbig</font><script>alert(1)</script></p>',
                ],
                [
                    'type' => 'custom_html',
                    'content' => '<p onclick="alert(1)">Sicherer <strong>Testinhalt</strong><script>alert(1)</script></p>',
                ],
                [
                    'type' => 'image_text',
                    'content' => 'Text neben dem Bild.',
                    'mediaUrl' => 'https://example.test/bild.jpg',
                    'mediaAlt' => 'Ein Testbild',
                    'mediaSource' => 'Foto: <strong>Waldbad-Team</strong>',
                    'layout' => 'image_right',
                    'imageWidthPercent' => 55,
                    'verticalAlignment' => 'top',
                    'textAlignment' => 'center',
                    'imageFit' => 'contain',
                ],
                [
                    'type' => 'image',
                    'content' => '',
                    'mediaUrl' => 'https://example.test/dekorativ.jpg',
                    'mediaAlt' => null,
                    'layout' => 'right',
                    'imageWidthPercent' => 65,
                ],
                [
                    'type' => 'event',
                    'content' => '',
                    'eventTitle' => '<strong>Sommerfest</strong>',
                    'eventDate' => '2026-08-15',
                    'eventTime' => '14:00',
                    'eventIdentifier' => 'event-sommerfest-2026',
                    'eventHelpEnabled' => true,
                    'eventHelpButtonLabel' => 'Ich möchte helfen!',
                    'eventActivities' => [[
                        'activityId' => $createdActivity['id'],
                        'requiredHelpers' => 1,
                    ]],
                    'mediaUrl' => null,
                    'mediaAlt' => null,
                ],
            ],
            'visible' => true,
            'showInNavigation' => true,
            'navigationPosition' => 1,
        ];

        $this->client->jsonRequest('POST', '/api/admin/v1/pages', $page);
        self::assertResponseStatusCodeSame(403);

        $this->client->jsonRequest('POST', '/api/admin/v1/pages/preview', $page, ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseIsSuccessful();
        $preview = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($preview);
        self::assertSame('preview', $preview['id']);
        self::assertSame('draft', $preview['status']);

        $this->client->jsonRequest('POST', '/api/admin/v1/pages', $page, ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseStatusCodeSame(201);
        $responseContent = $this->responseContent();
        self::assertStringNotContainsString('<script', $responseContent);
        self::assertStringNotContainsString('onclick', $responseContent);
        self::assertStringContainsString('\u003Cstrong\u003EFett\u003C\/strong\u003E', $responseContent);
        self::assertStringContainsString('color=\u0022#ff0000\u0022', $responseContent);
        self::assertStringContainsString('"layout":"image_right"', $responseContent);
        self::assertStringContainsString('"imageWidthPercent":55', $responseContent);
        self::assertStringContainsString('"verticalAlignment":"top"', $responseContent);
        self::assertStringContainsString('"textAlignment":"center"', $responseContent);
        self::assertStringContainsString('"imageFit":"contain"', $responseContent);
        self::assertStringContainsString('"mediaSource":"Foto: Waldbad-Team"', $responseContent);
        self::assertStringContainsString('"layout":"right"', $responseContent);
        self::assertStringContainsString('"imageWidthPercent":65', $responseContent);
        self::assertStringContainsString('"eventTitle":"Sommerfest"', $responseContent);
        self::assertStringContainsString('"eventDate":"2026-08-15"', $responseContent);
        self::assertStringContainsString('"eventTime":"14:00"', $responseContent);
        self::assertStringContainsString('"eventIdentifier":"event-sommerfest-2026"', $responseContent);
        self::assertStringContainsString('"eventHelpEnabled":true', $responseContent);
        $createdPage = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($createdPage);
        self::assertIsString($createdPage['id']);
        self::assertIsInt($createdPage['version']);
        self::assertSame('testseite', $createdPage['slug']);
        self::assertSame('draft', $createdPage['status']);

        $childPage = $page;
        $childPage['title'] = 'Test-Unterseite';
        $childPage['slug'] = 'test-unterseite';
        $childPage['navigationLabel'] = 'Unterseite';
        $childPage['parentId'] = $createdPage['id'];
        $this->client->jsonRequest('POST', '/api/admin/v1/pages', $childPage, ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseStatusCodeSame(201);
        $createdChild = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($createdChild);
        self::assertIsString($createdChild['id']);
        self::assertSame($createdPage['id'], $createdChild['parentId']);
        self::assertSame('testseite/test-unterseite', $createdChild['slug']);

        $this->client->jsonRequest('POST', sprintf('/api/admin/v1/pages/%s/publish', $createdPage['id']), [], ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseIsSuccessful();
        $publishedPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($publishedPage);
        self::assertIsInt($publishedPage['version']);

        $this->client->request('GET', '/api/public/v1/pages/testseite');
        self::assertResponseIsSuccessful();
        $publicPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($publicPage);
        self::assertSame('Testseite', $publicPage['title']);

        $eventReferencePayload = $page;
        $eventReferencePayload['title'] = 'Veranstaltungshinweis';
        $eventReferencePayload['slug'] = 'veranstaltungshinweis';
        $eventReferencePayload['navigationLabel'] = 'Veranstaltungshinweis';
        $eventReferencePayload['showInNavigation'] = false;
        $eventReferencePayload['blocks'] = [[
            'type' => 'event_reference',
            'content' => '',
            'embeddedPageId' => $createdPage['id'],
            'eventIdentifier' => 'event-sommerfest-2026',
            'mediaUrl' => 'https://example.test/veranstaltungshinweis.jpg',
            'mediaAlt' => 'Hinweis auf das Sommerfest',
            'layout' => 'image_right',
            'imageWidthPercent' => 65,
            'verticalAlignment' => 'top',
            'textAlignment' => 'center',
            'imageFit' => 'contain',
        ]];
        $this->client->jsonRequest('POST', '/api/admin/v1/pages', $eventReferencePayload, ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseStatusCodeSame(201);
        $eventReferencePage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($eventReferencePage);
        self::assertIsString($eventReferencePage['id']);
        $this->client->jsonRequest('POST', sprintf('/api/admin/v1/pages/%s/publish', $eventReferencePage['id']), [], ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/public/v1/pages/veranstaltungshinweis');
        self::assertResponseIsSuccessful();
        $publicEventReferencePage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($publicEventReferencePage);
        self::assertIsArray($publicEventReferencePage['blocks']);
        self::assertIsArray($publicEventReferencePage['blocks'][0]);
        self::assertSame('event_reference', $publicEventReferencePage['blocks'][0]['type']);
        self::assertSame($createdPage['id'], $publicEventReferencePage['blocks'][0]['embeddedPageId']);
        self::assertSame('event-sommerfest-2026', $publicEventReferencePage['blocks'][0]['eventIdentifier']);
        self::assertSame('https://example.test/veranstaltungshinweis.jpg', $publicEventReferencePage['blocks'][0]['mediaUrl']);
        self::assertSame('Hinweis auf das Sommerfest', $publicEventReferencePage['blocks'][0]['mediaAlt']);
        self::assertSame('image_right', $publicEventReferencePage['blocks'][0]['layout']);
        self::assertSame(65, $publicEventReferencePage['blocks'][0]['imageWidthPercent']);
        self::assertSame('top', $publicEventReferencePage['blocks'][0]['verticalAlignment']);
        self::assertSame('center', $publicEventReferencePage['blocks'][0]['textAlignment']);
        self::assertSame('contain', $publicEventReferencePage['blocks'][0]['imageFit']);

        $draftPayload = $page;
        $draftPayload['title'] = 'Überarbeiteter Entwurf';
        $draftPayload['slug'] = 'testseite-neu';
        $draftPayload['navigationLabel'] = 'Neuer Navigationstitel';
        $draftPayload['blocks'][0]['content'] = '<p>Noch nicht veröffentlichter Inhalt</p>';
        $draftPayload['version'] = $publishedPage['version'];
        $this->client->jsonRequest('PUT', '/api/admin/v1/pages/'.$createdPage['id'], $draftPayload, ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseIsSuccessful();
        $draftPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($draftPage);
        self::assertSame('draft', $draftPage['status']);
        self::assertNotNull($draftPage['publishedAt']);

        $this->client->request('GET', '/api/public/v1/pages/testseite');
        self::assertResponseIsSuccessful();
        $lastPublishedPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($lastPublishedPage);
        self::assertSame('Testseite', $lastPublishedPage['title']);
        self::assertStringNotContainsString('Noch nicht veröffentlichter Inhalt', $this->responseContent());
        $this->client->request('GET', '/api/public/v1/pages/testseite-neu');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/api/public/v1/navigation');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"label":"Test"', $this->responseContent());
        self::assertStringNotContainsString('Neuer Navigationstitel', $this->responseContent());

        $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', [
            'eventIdentifier' => 'event-sommerfest-2026',
            'firstName' => 'Erika',
            'lastName' => 'Musterfrau',
            'message' => '<b>Ich helfe beim Aufbau.</b>',
            'activityIds' => [$createdActivity['id']],
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(202);
        $helperResponse = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($helperResponse);
        self::assertIsString($helperResponse['id']);

        $this->client->request('GET', '/api/public/v1/event-activities/event-sommerfest-2026');
        self::assertResponseIsSuccessful();
        $availability = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($availability);
        self::assertIsArray($availability['items']);
        self::assertIsArray($availability['items'][0]);
        $availableActivity = $availability['items'][0];
        self::assertSame('Aufbau', $availableActivity['name']);
        self::assertSame(1, $availableActivity['requiredHelpers']);
        self::assertSame(1, $availableActivity['registeredHelpers']);

        $this->client->jsonRequest('POST', '/api/public/v1/event-help-requests', [
            'eventIdentifier' => 'event-sommerfest-2026',
            'firstName' => 'Max',
            'lastName' => 'Beispiel',
            'message' => '',
            'activityIds' => [$createdActivity['id']],
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(422);
        $capacityError = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($capacityError);
        self::assertIsArray($capacityError['error']);
        self::assertSame('Eine ausgewählte Aktivität ist bereits vollständig belegt.', $capacityError['error']['message']);

        $this->client->jsonRequest('POST', sprintf('/api/admin/v1/pages/%s/publish', $createdPage['id']), [], ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseIsSuccessful();
        $publishedPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($publishedPage);
        self::assertIsInt($publishedPage['version']);
        $this->client->request('GET', '/api/public/v1/pages/testseite');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/api/public/v1/pages/testseite-neu');
        self::assertResponseIsSuccessful();
        $republishedPublicPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($republishedPublicPage);
        self::assertSame('Überarbeiteter Entwurf', $republishedPublicPage['title']);

        $this->client->request('GET', '/api/admin/v1/event-help-requests');
        self::assertResponseIsSuccessful();
        $helpers = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($helpers);
        self::assertIsArray($helpers['items']);
        self::assertCount(1, $helpers['items']);
        self::assertIsArray($helpers['items'][0]);
        $helper = $helpers['items'][0];
        self::assertSame('Sommerfest', $helper['eventTitle']);
        self::assertSame('<b>Ich helfe beim Aufbau.</b>', $helper['message']);
        self::assertSame('new', $helper['status']);
        self::assertIsArray($helper['selectedActivities']);
        self::assertIsArray($helper['selectedActivities'][0]);
        self::assertSame('Aufbau', $helper['selectedActivities'][0]['name']);

        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests/'.$helperResponse['id'].'/participation', [
            'participated' => true,
            'intervals' => [
                ['fromTime' => '14:00', 'toTime' => '16:30'],
                ['fromTime' => '18:00', 'toTime' => '19:00'],
            ],
        ], ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseIsSuccessful();
        $participatedHelper = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($participatedHelper);
        self::assertSame('participated', $participatedHelper['status']);
        self::assertSame(210, $participatedHelper['participationMinutes']);
        self::assertIsArray($participatedHelper['participationIntervals']);
        self::assertCount(2, $participatedHelper['participationIntervals']);
        self::assertIsArray($participatedHelper['selectedActivities']);
        self::assertIsArray($participatedHelper['selectedActivities'][0]);
        self::assertSame('Aufbau', $participatedHelper['selectedActivities'][0]['name']);

        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests/'.$helperResponse['id'].'/participation', [
            'participated' => true,
            'intervals' => [
                ['fromTime' => '14:00', 'toTime' => '16:30'],
                ['fromTime' => '18:00', 'toTime' => '19:00'],
                ['fromTime' => '20:00', 'toTime' => '21:00'],
            ],
        ], ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseIsSuccessful();
        $editedHelper = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($editedHelper);
        self::assertSame(270, $editedHelper['participationMinutes']);
        self::assertIsArray($editedHelper['participationIntervals']);
        self::assertCount(3, $editedHelper['participationIntervals']);

        $this->client->jsonRequest('POST', '/api/admin/v1/event-help-requests/'.$helperResponse['id'].'/participation', [
            'participated' => false,
        ], ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseIsSuccessful();
        $absentHelper = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($absentHelper);
        self::assertSame('not_participated', $absentHelper['status']);
        self::assertSame(0, $absentHelper['participationMinutes']);
        self::assertSame([], $absentHelper['participationIntervals']);
        $this->client->jsonRequest('POST', sprintf('/api/admin/v1/pages/%s/publish', $createdChild['id']), [], ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseIsSuccessful();
        $publishedChild = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($publishedChild);
        self::assertIsInt($publishedChild['version']);
        self::assertSame('testseite/test-unterseite', $publishedChild['slug']);
        self::assertTrue($publishedChild['visible']);
        $this->client->request('GET', '/api/public/v1/pages/id/'.$createdChild['id']);
        self::assertResponseIsSuccessful();
        $publishedChildById = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($publishedChildById);
        self::assertSame('testseite/test-unterseite', $publishedChildById['slug']);
        $this->client->request('GET', '/api/public/v1/pages/testseite/test-unterseite');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/seite/testseite/test-unterseite');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/public/v1/navigation');
        self::assertResponseIsSuccessful();
        $navigation = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($navigation);
        self::assertIsArray($navigation['items']);
        self::assertCount(2, $navigation['items']);
        self::assertStringContainsString('"parentId":"'.$createdPage['id'].'"', $this->responseContent());

        $embeddedPagePayload = $page;
        $embeddedPagePayload['title'] = 'Seite mit Einbettung';
        $embeddedPagePayload['slug'] = 'seite-mit-einbettung';
        $embeddedPagePayload['navigationLabel'] = 'Einbettung';
        $embeddedPagePayload['blocks'] = [[
            'type' => 'embedded_page',
            'content' => '',
            'embeddedPageId' => $createdChild['id'],
        ]];
        $this->client->jsonRequest('POST', '/api/admin/v1/pages', $embeddedPagePayload, ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseStatusCodeSame(201);
        $embeddedPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($embeddedPage);
        self::assertIsString($embeddedPage['id']);
        self::assertIsArray($embeddedPage['blocks']);
        self::assertIsArray($embeddedPage['blocks'][0]);
        self::assertSame($createdChild['id'], $embeddedPage['blocks'][0]['embeddedPageId']);
        $this->client->jsonRequest('POST', sprintf('/api/admin/v1/pages/%s/publish', $embeddedPage['id']), [], ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseIsSuccessful();
        $publishedEmbeddedPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($publishedEmbeddedPage);
        self::assertIsInt($publishedEmbeddedPage['version']);
        $this->client->request('GET', '/api/public/v1/pages/id/'.$createdChild['id']);
        self::assertResponseIsSuccessful();

        $selfEmbeddingPayload = $embeddedPagePayload;
        $selfEmbeddingPayload['blocks'][0]['embeddedPageId'] = $embeddedPage['id'];
        $selfEmbeddingPayload['version'] = $publishedEmbeddedPage['version'];
        $this->client->jsonRequest('PUT', '/api/admin/v1/pages/'.$embeddedPage['id'], $selfEmbeddingPayload, ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseStatusCodeSame(422);

        $cyclePayload = $page;
        $cyclePayload['parentId'] = $createdChild['id'];
        $cyclePayload['version'] = $publishedPage['version'];
        $this->client->jsonRequest('PUT', '/api/admin/v1/pages/'.$createdPage['id'], $cyclePayload, ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseStatusCodeSame(422);

        $hiddenChildPayload = $childPage;
        $hiddenChildPayload['visible'] = false;
        $hiddenChildPayload['version'] = $publishedChild['version'];
        $this->client->jsonRequest('PUT', '/api/admin/v1/pages/'.$createdChild['id'], $hiddenChildPayload, ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseIsSuccessful();
        $hiddenChild = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($hiddenChild);
        self::assertFalse($hiddenChild['visible']);
        $this->client->jsonRequest('POST', sprintf('/api/admin/v1/pages/%s/publish', $createdChild['id']), [], ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/public/v1/pages/testseite/test-unterseite');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/api/public/v1/navigation');
        self::assertResponseIsSuccessful();
        $hiddenNavigation = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($hiddenNavigation);
        self::assertIsArray($hiddenNavigation['items']);
        self::assertCount(2, $hiddenNavigation['items']);
        $this->client->request('GET', '/api/admin/v1/pages');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"visible":false', $this->responseContent());

        $temporaryImage = tempnam(sys_get_temp_dir(), 'waldbad-upload-');
        $imageContents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        if ($temporaryImage === false || $imageContents === false || file_put_contents($temporaryImage, $imageContents) === false) {
            throw new \RuntimeException('Das Testbild konnte nicht erzeugt werden.');
        }

        $this->client->request(
            method: 'POST',
            uri: '/api/admin/v1/media/images',
            parameters: ['source' => 'Foto: Waldbad-Team'],
            files: ['image' => new UploadedFile($temporaryImage, 'waldbad.png', 'image/png', null, true)],
            server: ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']],
        );
        self::assertResponseStatusCodeSame(201);
        $storedImage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($storedImage);
        self::assertSame('image/png', $storedImage['mimeType']);
        self::assertSame(1, $storedImage['width']);
        self::assertSame(1, $storedImage['height']);
        self::assertIsString($storedImage['url']);
        self::assertSame('Foto: Waldbad-Team', $storedImage['source']);
        $storedPath = dirname(__DIR__, 2).'/public'.$storedImage['url'];
        self::assertFileExists($storedPath);

        $this->client->request('GET', '/api/admin/v1/media/images');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(str_replace('/', '\/', $storedImage['url']), $this->responseContent());
        self::assertStringContainsString('Foto: Waldbad-Team', $this->responseContent());

        $this->client->jsonRequest('PATCH', '/api/admin/v1/media/images/source', [
            'url' => $storedImage['url'],
            'source' => 'Illustration: Naturbad Borkheide e.V.',
        ], ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']]);
        self::assertResponseIsSuccessful();
        $updatedImage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($updatedImage);
        self::assertSame('Illustration: Naturbad Borkheide e.V.', $updatedImage['source']);

        unlink($storedPath);
        $sourcePath = $storedPath.'.source.txt';
        if (is_file($sourcePath)) {
            unlink($sourcePath);
        }
    }

    public function testPagesCanBeMovedDuplicatedAndDeletedSafely(): void
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: 'structure-admin@example.test',
            displayName: 'Struktur-Admin',
            plainPassword: 'Ein-sicheres-Strukturpasswort-2026',
            roles: [Role::SuperAdmin],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login', [
            'email' => 'structure-admin@example.test',
            'password' => 'Ein-sicheres-Strukturpasswort-2026',
        ]);
        self::assertResponseIsSuccessful();
        $login = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($login);
        self::assertIsString($login['csrfToken']);
        $headers = ['HTTP_X_CSRF_TOKEN' => $login['csrfToken']];

        $page = [
            'title' => 'Erste Seite',
            'slug' => 'erste-seite',
            'navigationLabel' => 'Erste Seite',
            'parentId' => null,
            'blocks' => [['type' => 'rich_text', 'content' => '<p>Inhalt</p>']],
            'visible' => true,
            'showInNavigation' => true,
            'navigationPosition' => 0,
            'seoTitle' => 'Erste Seite',
        ];
        $this->client->jsonRequest('POST', '/api/admin/v1/pages', $page, $headers);
        self::assertResponseStatusCodeSame(201);
        $firstPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($firstPage);
        self::assertIsString($firstPage['id']);

        $page['title'] = 'Zweite Seite';
        $page['slug'] = 'zweite-seite';
        $page['navigationLabel'] = 'Zweite Seite';
        $page['navigationPosition'] = 1;
        $this->client->jsonRequest('POST', '/api/admin/v1/pages', $page, $headers);
        self::assertResponseStatusCodeSame(201);
        $secondPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($secondPage);
        self::assertIsString($secondPage['id']);

        $this->client->jsonRequest('POST', '/api/admin/v1/pages/'.$secondPage['id'].'/move/up', [], $headers);
        self::assertResponseIsSuccessful();
        $movedPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($movedPage);
        self::assertSame(0, $movedPage['navigationPosition']);

        $this->client->jsonRequest('POST', '/api/admin/v1/pages/'.$firstPage['id'].'/duplicate', [], $headers);
        self::assertResponseStatusCodeSame(201);
        $duplicate = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($duplicate);
        self::assertIsString($duplicate['id']);
        self::assertSame('erste-seite-kopie', $duplicate['slug']);
        self::assertSame('draft', $duplicate['status']);
        self::assertFalse($duplicate['visible']);
        self::assertFalse($duplicate['showInNavigation']);
        self::assertSame($firstPage['blocks'], $duplicate['blocks']);

        $this->client->jsonRequest('DELETE', '/api/admin/v1/pages/'.$duplicate['id'], [], $headers);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/api/admin/v1/pages');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString($duplicate['id'], $this->responseContent());

        $page['title'] = 'Unterseite';
        $page['slug'] = 'unterseite';
        $page['navigationLabel'] = 'Unterseite';
        $page['parentId'] = $secondPage['id'];
        $this->client->jsonRequest('POST', '/api/admin/v1/pages', $page, $headers);
        self::assertResponseStatusCodeSame(201);
        $subPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($subPage);
        self::assertSame('zweite-seite/unterseite', $subPage['slug']);

        $page['title'] = 'Verschiebbare Hauptseite';
        $page['slug'] = 'verschiebbare-hauptseite';
        $page['navigationLabel'] = 'Verschiebbare Hauptseite';
        $page['parentId'] = null;
        $this->client->jsonRequest('POST', '/api/admin/v1/pages', $page, $headers);
        self::assertResponseStatusCodeSame(201);
        $movablePage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($movablePage);
        self::assertIsString($movablePage['id']);
        self::assertIsInt($movablePage['version']);

        $this->client->jsonRequest('PUT', '/api/admin/v1/pages/'.$movablePage['id'].'/position', [
            'parentId' => $secondPage['id'],
            'navigationPosition' => 0,
            'version' => $movablePage['version'],
        ], $headers);
        self::assertResponseIsSuccessful();
        $reorderedPage = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($reorderedPage);
        self::assertSame($secondPage['id'], $reorderedPage['parentId']);
        self::assertSame(0, $reorderedPage['navigationPosition']);

        $this->client->jsonRequest('DELETE', '/api/admin/v1/pages/'.$secondPage['id'], [], $headers);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Unterseiten', $this->responseContent());

        $initialize = self::getContainer()->get(InitializeSiteUseCase::class);
        if (!$initialize instanceof InitializeSiteUseCase) {
            throw new \LogicException('Die Site-Initialisierung ist im Testcontainer nicht verfügbar.');
        }
        $initialize->execute();
        $this->client->request('GET', '/api/admin/v1/pages');
        $pages = json_decode($this->responseContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($pages);
        self::assertIsArray($pages['items']);
        $startPage = array_values(array_filter($pages['items'], static fn (mixed $item): bool => is_array($item) && ($item['slug'] ?? null) === 'startseite'));
        self::assertCount(1, $startPage);
        self::assertIsString($startPage[0]['id']);
        $this->client->jsonRequest('DELETE', '/api/admin/v1/pages/'.$startPage[0]['id'], [], $headers);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Startseite', $this->responseContent());
    }

    private function responseContent(): string
    {
        $content = $this->client->getResponse()->getContent();
        if ($content === false) {
            throw new \RuntimeException('Die HTTP-Antwort besitzt keinen lesbaren Inhalt.');
        }

        return $content;
    }
}
