<?php

namespace App\Tests\Functional\Scenarios\Rental;

use App\Data\Membership\ContributionRate\Entity\ContributionRateEntity;
use App\Data\Membership\Member\Entity\MemberNumberSequenceEntity;
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

/**
 * Sauna-Dienstplan: Dienstzeitpunkte (Einlass, Übergang, Abschließen) aus Saison und Buchungen, Eintragen über die Mitgliedersuche.
 */
final class SaunaDutyWorkflowTest extends WebTestCase
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
        $entityManager->persist(new MemberNumberSequenceEntity(id: 1, nextValue: 1));
        // Vorbild: `EventHelpRequestMemberLinkWorkflowTest::seedContributionRates()`.
        $entityManager->persist(new ContributionRateEntity(
            id: 'rate-1',
            category: 'individual_senior',
            label: 'Einzelperson Erwachsen',
            amountCents: 5000,
            period: 'yearly',
            personGroup: 'individual',
            minAge: 21,
            maxAge: null,
            pendingLabel: null,
            pendingAmountCents: null,
            pendingPeriod: null,
            pendingPersonGroup: null,
            pendingMinAge: null,
            pendingMaxAge: null,
            pendingValidFrom: null,
        ));
        $entityManager->flush();
    }

    public function testBookedEntryTimesRequireDutyAndAreStaffedViaMemberSearch(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $tomorrow = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');

        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->member('Vera', 'Dienstbereit', 'board'), $headers);
        self::assertResponseStatusCodeSame(201);
        $boardMemberId = $this->responseData()['id'];
        self::assertIsString($boardMemberId);
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->member('Otto', 'Dienstbereit', 'member'), $headers);
        self::assertResponseStatusCodeSame(201);

        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons', [
            'startsOn' => $tomorrow,
            'endsOn' => null,
            'slotDurationMinutes' => 120,
            'openingHours' => array_map(
                static fn (int $weekday): array => ['weekday' => $weekday, 'startTime' => '15:00', 'endTime' => '23:00'],
                range(1, 7),
            ),
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        // Buchungen über zwei Einheiten (4 Stunden), damit ein „laufender“ Zeitpunkt entsteht; Vorlauf 1 Tag für morgen.
        $this->client->jsonRequest('PUT', '/api/admin/v1/sauna-terms', [
            'priceCents' => 4000,
            'priceUnitMinutes' => 240,
            'minPersons' => 2,
            'maxPersons' => 6,
            'leadDays' => 1,
        ], $headers);
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [
            'date' => $tomorrow,
            'startTime' => '15:00',
            'endTime' => '19:00',
            'personCount' => 2,
            'participants' => [['firstName' => 'Erika', 'lastName' => 'Musterfrau'], ['firstName' => 'Gast', 'lastName' => 'Eins']],
            'firstName' => 'Erika',
            'lastName' => 'Musterfrau',
            'birthDate' => '1990-01-01',
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(202);
        $bookingId = $this->responseData()['id'];
        self::assertIsString($bookingId);
        self::assertSame(['15:00 requested entry', '17:00 running none', '19:00 requested closing', '21:00 free none', '23:00 free none'], $this->pointStates($tomorrow));

        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-bookings/'.$bookingId.'/accept', ['dutyRequired' => false], $headers);
        self::assertResponseIsSuccessful();
        self::assertFalse($this->responseData()['dutyRequired']);
        self::assertSame(['15:00 required entry', '17:00 running none', '19:00 required closing', '21:00 free none', '23:00 free none'], $this->pointStates($tomorrow));
        self::assertSame([true, false, true, false, false], array_column($this->points($tomorrow), 'dutyWaived'));

        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-bookings/'.$bookingId.'/duty', ['dutyRequired' => true], $headers);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->responseData()['dutyRequired']);
        self::assertSame([false, false, false, false, false], array_column($this->points($tomorrow), 'dutyWaived'));

        $this->client->request('GET', '/api/admin/v1/sauna-duties/candidates?search=Dienstbereit');
        self::assertResponseIsSuccessful();
        $candidates = $this->responseData()['items'];
        self::assertIsArray($candidates);
        self::assertCount(2, $candidates);
        self::assertIsArray($candidates[0]);
        self::assertSame($boardMemberId, $candidates[0]['id']);
        self::assertTrue($candidates[0]['boardMember']);

        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-duties', ['date' => $tomorrow, 'time' => '16:00', 'memberId' => $boardMemberId], $headers);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-duties', ['date' => $tomorrow, 'time' => '15:00', 'memberId' => $boardMemberId], $headers);
        self::assertResponseStatusCodeSame(201);
        $dutyId = $this->responseData()['id'];
        self::assertIsString($dutyId);
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-duties', ['date' => $tomorrow, 'time' => '15:00', 'memberId' => $boardMemberId], $headers);
        self::assertResponseStatusCodeSame(422);

        $point = $this->points($tomorrow)[0];
        $duties = $point['duties'];
        self::assertIsArray($duties);
        self::assertCount(1, $duties);
        self::assertIsArray($duties[0]);
        self::assertSame('Vera', $duties[0]['firstName']);

        $this->client->request('DELETE', '/api/admin/v1/sauna-duties/'.$dutyId, server: $headers);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->points($tomorrow)[0]['duties']);
    }

    public function testDutyCanBeWaivedPerTimeAndForTheWholeDay(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $tomorrow = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons', [
            'startsOn' => $tomorrow,
            'endsOn' => null,
            'slotDurationMinutes' => 120,
            'openingHours' => array_map(
                static fn (int $weekday): array => ['weekday' => $weekday, 'startTime' => '15:00', 'endTime' => '19:00'],
                range(1, 7),
            ),
        ], $headers);
        self::assertResponseStatusCodeSame(201);

        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-duties/waivers', ['date' => $tomorrow, 'time' => '17:00'], $headers);
        self::assertResponseStatusCodeSame(201);
        $pointWaiverId = $this->responseData()['id'];
        self::assertIsString($pointWaiverId);
        self::assertSame([false, true, false], array_column($this->points($tomorrow), 'dutyWaived'));
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-duties/waivers', ['date' => $tomorrow, 'time' => '17:00'], $headers);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-duties/waivers', ['date' => $tomorrow], $headers);
        self::assertResponseStatusCodeSame(201);
        $dayWaiverId = $this->responseData()['id'];
        self::assertIsString($dayWaiverId);
        self::assertSame($dayWaiverId, $this->day($tomorrow)['waiverId']);
        self::assertSame([true, true, true], array_column($this->points($tomorrow), 'dutyWaived'));
        self::assertSame([null, null, null], array_column($this->points($tomorrow), 'waiverId'), 'Der Tagesvermerk ersetzt den Einzelvermerk.');

        $this->client->request('DELETE', '/api/admin/v1/sauna-duties/waivers/'.$dayWaiverId, server: $headers);
        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->day($tomorrow)['waiverId']);
        self::assertSame([false, false, false], array_column($this->points($tomorrow), 'dutyWaived'));
        $this->client->request('DELETE', '/api/admin/v1/sauna-duties/waivers/'.$pointWaiverId, server: $headers);
        self::assertResponseStatusCodeSame(404);
    }

    public function testRosterRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/v1/sauna-duties?from=2026-10-01&days=1');

        self::assertResponseStatusCodeSame(401);
    }

    /** @return list<string> */
    private function pointStates(string $date): array
    {
        return array_map(static function (array $point): string {
            self::assertIsString($point['time']);
            self::assertIsString($point['state']);
            self::assertIsString($point['kind']);

            return $point['time'].' '.$point['state'].' '.$point['kind'];
        }, $this->points($date));
    }

    /** @return array<array-key, mixed> */
    private function day(string $date): array
    {
        $this->client->request('GET', '/api/admin/v1/sauna-duties?from='.$date.'&days=1');
        self::assertResponseIsSuccessful();
        $days = $this->responseData()['days'];
        self::assertIsArray($days);
        self::assertIsArray($days[0]);

        return $days[0];
    }

    /** @return list<array<array-key, mixed>> */
    private function points(string $date): array
    {
        $points = $this->day($date)['points'];
        self::assertIsArray($points);
        $result = [];
        foreach ($points as $point) {
            self::assertIsArray($point);
            $result[] = $point;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function member(string $firstName, string $lastName, string $function): array
    {
        return [
            'primaryMemberNumber' => null,
            'salutation' => 'ms',
            'lastName' => $lastName,
            'firstName' => $firstName,
            'birthDate' => '1980-04-12',
            'street' => 'Kirchanger 14',
            'postalCode' => '14822',
            'city' => 'Borkheide',
            'email' => null,
            'phone' => null,
            'familyRole' => 'none',
            'joinedAt' => '2026-01-01',
            'leftAt' => null,
            'function' => $function,
            'accountHolder' => $firstName.' '.$lastName,
            'iban' => 'DE89370400440532013000',
            'bankName' => 'Testbank',
            'mandateReference' => null,
            'paymentMethod' => 'sepa_direct_debit',
            'paymentInterval' => 'yearly',
            'paymentDay' => 'first',
            'payerType' => 'self_payer',
            'payerMemberId' => null,
            'nextBookingMonth' => null,
            'nextBookingYear' => null,
        ];
    }

    private function loginAsAdmin(): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: 'sauna-duty-admin@example.test',
            displayName: 'Sauna Admin',
            roles: [Role::SuperAdmin],
            moduleAccess: [
                new ModuleAccess(CmsModule::RentalSauna, ModuleRole::Editor),
                new ModuleAccess(CmsModule::Members, ModuleRole::Editor),
            ],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login-requests', ['email' => 'sauna-duty-admin@example.test']);
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
            $response[(string) $key] = $value;
        }

        return $response;
    }
}
