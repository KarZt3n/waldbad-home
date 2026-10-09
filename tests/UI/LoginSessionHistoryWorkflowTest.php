<?php

namespace App\Tests\UI;

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

final class LoginSessionHistoryWorkflowTest extends WebTestCase
{
    private const string EMAIL = 'session-admin@example.test';
    private const string USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';

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

        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: self::EMAIL,
            displayName: 'Sitzungs Admin',
            roles: [Role::SuperAdmin],
            moduleAccess: [new ModuleAccess(CmsModule::UserManagement, ModuleRole::Viewer)],
        ));
    }

    public function testLoginsAreRecordedWithTheirEndReason(): void
    {
        $csrfToken = $this->login();

        $this->client->request('POST', '/api/auth/v1/refresh');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/admin/v1/login-sessions');
        self::assertResponseIsSuccessful();
        $sessions = $this->sessions();
        self::assertCount(1, $sessions);
        self::assertSame('Sitzungs Admin', $sessions[0]['userDisplayName']);
        self::assertSame(self::USER_AGENT, $sessions[0]['userAgent']);
        self::assertNull($sessions[0]['endReason']);
        self::assertNull($sessions[0]['endedAt']);

        $this->client->jsonRequest('POST', '/api/auth/v1/logout', ['reason' => 'inactivity'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(204);

        $this->login();
        $this->client->request('GET', '/api/admin/v1/login-sessions');
        $sessions = $this->sessions();
        self::assertCount(2, $sessions);
        self::assertNull($sessions[0]['endReason'], 'Die neue Anmeldung ist noch aktiv.');
        self::assertSame('inactivity', $sessions[1]['endReason']);
        self::assertIsString($sessions[1]['endedAt']);
    }

    private function login(): string
    {
        $this->client->jsonRequest('POST', '/api/auth/v1/login-requests', ['email' => self::EMAIL]);
        $this->client->jsonRequest('POST', '/api/auth/v1/login', ['token' => FixedSecureTokenGenerator::TOKEN], ['HTTP_USER_AGENT' => self::USER_AGENT]);
        self::assertResponseIsSuccessful();
        $csrfToken = $this->responseData()['csrfToken'];
        self::assertIsString($csrfToken);

        return $csrfToken;
    }

    /** @return list<array<mixed>> */
    private function sessions(): array
    {
        $items = $this->responseData()['items'];
        self::assertIsArray($items);
        $sessions = [];
        foreach ($items as $item) {
            self::assertIsArray($item);
            $sessions[] = $item;
        }

        return $sessions;
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
