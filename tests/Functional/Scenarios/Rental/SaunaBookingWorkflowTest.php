<?php

namespace App\Tests\Functional\Scenarios\Rental;

use App\Logic\Content\Site\UseCase\InitializeSiteUseCase;
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

final class SaunaBookingWorkflowTest extends WebTestCase
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

    public function testSeasonDrivesCalendarAndBookingsAreModerated(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $tomorrow = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $allWeekdays = array_map(
            static fn (int $weekday): array => ['weekday' => $weekday, 'startTime' => '10:00', 'endTime' => '14:00'],
            range(1, 7),
        );

        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons', [
            'name' => 'Wintersaison',
            'startsOn' => $tomorrow,
            'endsOn' => null,
            'slotDurationMinutes' => 60,
            'openingHours' => $allWeekdays,
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        self::assertNull($this->responseData()['endsOn']);
        $firstSeasonId = $this->responseData()['id'];

        // Eine weitere Saison schließt die offene automatisch zu ihrem Beginn ab, ohne deren Enddatum zu setzen.
        $successorStart = (new \DateTimeImmutable('+30 days'))->format('Y-m-d');
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons', [
            'name' => 'Nachfolgesaison',
            'startsOn' => $successorStart,
            'endsOn' => null,
            'slotDurationMinutes' => 60,
            'openingHours' => $allWeekdays,
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $successorId = $this->responseData()['id'];
        $this->client->request('GET', '/api/admin/v1/sauna-seasons');
        $seasons = $this->responseData()['items'];
        self::assertIsArray($seasons);
        foreach ($seasons as $season) {
            self::assertIsArray($season);
            self::assertNull($season['endsOn']);
            self::assertSame($season['id'] === $firstSeasonId ? $successorStart : null, $season['closedOn']);
        }
        self::assertIsString($successorId);
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons/'.$successorId.'/close', [], $headers);
        self::assertResponseIsSuccessful();
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons/'.$successorId.'/close', [], $headers);
        self::assertResponseStatusCodeSame(422);

        self::assertSame(['10:00 free', '11:00 free', '12:00 free', '13:00 free'], $this->calendarStates($tomorrow));

        $this->client->jsonRequest('PUT', '/api/admin/v1/sauna-terms', [
            'priceCents' => 2000,
            'priceUnitMinutes' => 120,
            'minPersons' => 1,
            'maxPersons' => 6,
        ], $headers);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('PUT', '/api/admin/v1/sauna-terms', [
            'priceCents' => 2400,
            'priceUnitMinutes' => 120,
            'minPersons' => 2,
            'maxPersons' => 5,
        ], $headers);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/public/v1/sauna/calendar?from='.$tomorrow.'&days=1');
        $publicTerms = $this->responseData()['terms'];
        self::assertIsArray($publicTerms);
        self::assertSame(5, $publicTerms['maxPersons']);

        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [
            'date' => $tomorrow,
            'startTime' => '10:00',
            'endTime' => '11:00',
            'personCount' => 1,
            'firstName' => 'Allein',
            'lastName' => 'Saunierer',
            'birthDate' => '1990-01-01',
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [
            'date' => $tomorrow,
            'startTime' => '10:00',
            'endTime' => '12:00',
            'personCount' => 4,
            'firstName' => 'Erika',
            'lastName' => 'Musterfrau',
            'birthDate' => '1990-01-01',
            'email' => 'erika@example.test',
            'message' => 'Wir kommen zu dritt.',
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(202);
        $bookingId = $this->responseData()['id'];
        self::assertIsString($bookingId);

        self::assertSame(['10:00 booked', '11:00 booked', '12:00 free', '13:00 free'], $this->calendarStates($tomorrow));
        self::assertStringNotContainsString('Musterfrau', (string) $this->client->getResponse()->getContent());

        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [
            'date' => $tomorrow,
            'startTime' => '11:00',
            'endTime' => '12:00',
            'personCount' => 2,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'birthDate' => '1985-05-05',
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('GET', '/api/admin/v1/sauna-bookings');
        self::assertResponseIsSuccessful();
        $list = $this->responseData();
        self::assertSame(1, $list['total']);
        $items = $list['items'];
        self::assertIsArray($items);
        self::assertIsArray($items[0]);
        self::assertSame('open', $items[0]['status']);
        self::assertSame(4, $items[0]['personCount']);
        self::assertSame(2400, $items[0]['priceCents']);
        self::assertNull($items[0]['memberId']);
        self::assertNull($items[0]['memberContact']);

        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-bookings/'.$bookingId.'/accept', [], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame('accepted', $this->responseData()['status']);

        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-bookings/'.$bookingId.'/reject', [], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame('rejected', $this->responseData()['status']);

        self::assertSame(['10:00 free', '11:00 free', '12:00 free', '13:00 free'], $this->calendarStates($tomorrow));
    }

    public function testCalendarReportsRunningOrUpcomingSeasonButNotExpiredOnes(): void
    {
        $this->client->request('GET', '/api/public/v1/sauna/calendar');
        self::assertResponseIsSuccessful();
        self::assertNull($this->responseData()['season']);

        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $this->createSeason('Abgelaufene Saison', '-60 days', '-30 days', $headers);
        $this->client->request('GET', '/api/public/v1/sauna/calendar');
        self::assertNull($this->responseData()['season']);

        $upcomingStart = (new \DateTimeImmutable('+40 days'))->format('Y-m-d');
        $upcomingId = $this->createSeason('Kommende Saison', '+40 days', null, $headers);
        $this->client->request('GET', '/api/public/v1/sauna/calendar');
        self::assertSame(['name' => 'Kommende Saison', 'startsOn' => $upcomingStart], $this->responseData()['season']);

        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons/'.$upcomingId.'/close', [], $headers);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/public/v1/sauna/calendar');
        self::assertNull($this->responseData()['season']);
    }

    /** @param array<string, string> $headers */
    private function createSeason(string $name, string $startsOn, ?string $endsOn, array $headers): string
    {
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons', [
            'name' => $name,
            'startsOn' => (new \DateTimeImmutable($startsOn))->format('Y-m-d'),
            'endsOn' => $endsOn === null ? null : (new \DateTimeImmutable($endsOn))->format('Y-m-d'),
            'slotDurationMinutes' => 60,
            'openingHours' => [['weekday' => 1, 'startTime' => '10:00', 'endTime' => '12:00']],
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        $id = $this->responseData()['id'];
        self::assertIsString($id);

        return $id;
    }

    public function testBookingOutsideSeasonIsRejected(): void
    {
        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [
            'date' => (new \DateTimeImmutable('tomorrow'))->format('Y-m-d'),
            'startTime' => '10:00',
            'endTime' => '11:00',
            'personCount' => 2,
            'firstName' => 'Erika',
            'lastName' => 'Musterfrau',
            'birthDate' => '1990-01-01',
            'privacyAccepted' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testIndividualRequestWithFreeTimeWorksWithoutSeason(): void
    {
        $date = (new \DateTimeImmutable('+3 days'))->format('Y-m-d');
        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [
            'date' => $date,
            'startTime' => '07:30',
            'endTime' => '09:45',
            'individual' => true,
            'personCount' => 3,
            'firstName' => 'Erika',
            'lastName' => 'Musterfrau',
            'birthDate' => '1990-01-01',
            'message' => 'Geburtstagsrunde',
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(202);

        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [
            'date' => $date,
            'startTime' => '09:00',
            'endTime' => '10:00',
            'individual' => true,
            'personCount' => 2,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'birthDate' => '1985-05-05',
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(422);

        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $this->client->request('GET', '/api/admin/v1/sauna-bookings', [], [], $headers);
        $items = $this->responseData()['items'];
        self::assertIsArray($items);
        self::assertCount(1, $items);
        self::assertIsArray($items[0]);
        self::assertTrue($items[0]['individual']);
        self::assertSame(2250, $items[0]['priceCents']);
    }

    public function testAdminEndpointsRequireAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/v1/sauna-bookings');

        self::assertContains($this->client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testSiteInitializationCreatesRentalPageWithSaunaSubpage(): void
    {
        $initialize = self::getContainer()->get(InitializeSiteUseCase::class);
        if (!$initialize instanceof InitializeSiteUseCase) {
            throw new \LogicException('Die Seiteninitialisierung ist im Testcontainer nicht verfügbar.');
        }
        $initialize->execute();

        $this->client->request('GET', '/api/public/v1/pages/vermietung');
        self::assertResponseIsSuccessful();
        $parentId = $this->responseData()['id'];

        $this->client->request('GET', '/api/public/v1/pages/vermietung/sauna');
        self::assertResponseIsSuccessful();
        $page = $this->responseData();
        self::assertSame($parentId, $page['parentId']);
        $blocks = $page['blocks'];
        self::assertIsArray($blocks);
        $extensionKeys = array_map(static fn (mixed $block): mixed => is_array($block) ? ($block['extensionKey'] ?? null) : null, $blocks);
        self::assertContains('sauna', $extensionKeys);
    }

    /** @return list<string> */
    private function calendarStates(string $date): array
    {
        $this->client->request('GET', '/api/public/v1/sauna/calendar?from='.$date.'&days=1');
        self::assertResponseIsSuccessful();
        $days = $this->responseData()['days'];
        self::assertIsArray($days);
        self::assertIsArray($days[0]);
        $slots = $days[0]['slots'];
        self::assertIsArray($slots);

        $states = [];
        foreach ($slots as $slot) {
            self::assertIsArray($slot);
            self::assertIsString($slot['startTime']);
            self::assertIsString($slot['state']);
            $states[] = $slot['startTime'].' '.$slot['state'];
        }

        return $states;
    }

    private function loginAsAdmin(): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: 'sauna-admin@example.test',
            displayName: 'Sauna Admin',
            roles: [Role::SuperAdmin],
            moduleAccess: [new ModuleAccess(CmsModule::RentalSauna, ModuleRole::Editor)],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login-requests', ['email' => 'sauna-admin@example.test']);
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
        if (!is_string($content)) {
            throw new \LogicException('Die Testantwort enthält keinen lesbaren Inhalt.');
        }
        if ($content === '') {
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
