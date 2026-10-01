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

        // Eine abgeschlossene Saison lässt sich wieder eröffnen, solange keine andere offen ist.
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons/'.$successorId.'/reopen', [], $headers);
        self::assertResponseIsSuccessful();
        self::assertNull($this->responseData()['closedOn']);
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons/'.$successorId.'/reopen', [], $headers);
        self::assertResponseStatusCodeSame(422);
        self::assertIsString($firstSeasonId);
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons/'.$firstSeasonId.'/reopen', [], $headers);
        self::assertResponseStatusCodeSame(422);
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons/'.$successorId.'/close', [], $headers);
        self::assertResponseIsSuccessful();

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
            'endTime' => '12:00',
            'personCount' => 1,
            'participants' => self::names(1),
            'firstName' => 'Allein',
            'lastName' => 'Saunierer',
            'birthDate' => '1990-01-01',
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('PUT', '/api/admin/v1/email-settings/notifications/sauna_booking_submitted', [
            'recipients' => ['sauna-team@example.test'],
        ], $headers);
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [
            'date' => $tomorrow,
            'startTime' => '10:00',
            'endTime' => '12:00',
            'personCount' => 4,
            'participants' => self::names(4),
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
        // Die neue Anmeldung geht an die unter „Benachrichtigungen“ hinterlegten Empfänger.
        self::assertQueuedEmailCount(1);
        $notification = self::getMailerMessage();
        self::assertNotNull($notification);
        self::assertEmailAddressContains($notification, 'To', 'sauna-team@example.test');
        self::assertEmailTextBodyContains($notification, 'Erika Musterfrau hat die Sauna angefragt');
        self::assertEmailTextBodyContains($notification, "Zeitfenster aus dem Kalender:\n\n".(new \DateTimeImmutable($tomorrow))->format('d.m.Y').", 10:00–12:00 Uhr,\n- 4 Personen (Erika Musterfrau, Gast 1, Gast 2, Gast 3)\n- Preis: 24,00 €");
        self::assertEmailTextBodyContains($notification, 'Nachricht: Wir kommen zu dritt.');

        self::assertSame(['10:00 booked', '11:00 booked', '12:00 free', '13:00 free'], $this->calendarStates($tomorrow));
        self::assertStringNotContainsString('Musterfrau', (string) $this->client->getResponse()->getContent());

        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [
            'date' => $tomorrow,
            'startTime' => '11:00',
            'endTime' => '13:00',
            'personCount' => 2,
            'participants' => self::names(2),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'birthDate' => '1985-05-05',
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(422);

        // Ohne die Namen aller Personen ist keine Kalender-Anfrage möglich.
        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [
            'date' => $tomorrow,
            'startTime' => '12:00',
            'endTime' => '14:00',
            'personCount' => 3,
            'participants' => self::names(2),
            'firstName' => 'Erika',
            'lastName' => 'Musterfrau',
            'birthDate' => '1990-01-01',
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(422);

        // Kürzer als die Mindestdauer (Bezugsdauer der Konditionen, standardmäßig 2 Stunden) ist nicht anfragbar.
        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [
            'date' => $tomorrow,
            'startTime' => '12:00',
            'endTime' => '13:00',
            'personCount' => 2,
            'participants' => self::names(2),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'birthDate' => '1985-05-05',
            'privacyAccepted' => true,
        ]);
        self::assertResponseStatusCodeSame(422);
        $error = $this->responseData()['error'];
        self::assertIsArray($error);
        self::assertSame('Eine Sauna-Anfrage muss mindestens 2 Stunden dauern.', $error['message']);

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
        // Die anfragende Person erhält eine Bestätigung mit ihren Angaben.
        self::assertQueuedEmailCount(1);
        $confirmation = self::getMailerMessage();
        self::assertNotNull($confirmation);
        self::assertEmailAddressContains($confirmation, 'To', 'erika@example.test');
        self::assertEmailTextBodyContains($confirmation, 'deine Sauna-Anfrage wurde angenommen');
        self::assertEmailTextBodyContains($confirmation, (new \DateTimeImmutable($tomorrow))->format('d.m.Y').", 10:00–12:00 Uhr,\n- 4 Personen (Erika Musterfrau, Gast 1, Gast 2, Gast 3)\n- Preis: 24,00 €");
        self::assertEmailTextBodyContains($confirmation, 'Nachricht: Wir kommen zu dritt.');

        // Storno eines angenommenen Termins gibt den Zeitraum frei; ein zweites Storno ist nicht möglich.
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-bookings/'.$bookingId.'/cancel', ['notify' => true], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame('cancelled', $this->responseData()['status']);
        self::assertQueuedEmailCount(1);
        $cancellation = self::getMailerMessage();
        self::assertNotNull($cancellation);
        self::assertEmailAddressContains($cancellation, 'To', 'erika@example.test');
        self::assertEmailTextBodyContains($cancellation, "dein Sauna-Termin wurde storniert:\n\n".(new \DateTimeImmutable($tomorrow))->format('d.m.Y').', 10:00–12:00 Uhr,');
        self::assertSame(['10:00 free', '11:00 free', '12:00 free', '13:00 free'], $this->calendarStates($tomorrow));
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-bookings/'.$bookingId.'/cancel', [], $headers);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-bookings/'.$bookingId.'/reject', [], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame('rejected', $this->responseData()['status']);

        self::assertSame(['10:00 free', '11:00 free', '12:00 free', '13:00 free'], $this->calendarStates($tomorrow));
    }

    /**
     * Schließzeiten der Saison: Der Tag erscheint im Kalender als „Geschlossen“ ohne Zeiten, und
     * weder eine Kalender- noch eine individuelle Anfrage für diesen Tag wird angenommen.
     */
    public function testClosureDaysAreShownClosedAndRejectRequests(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $closedDay = (new \DateTimeImmutable('+2 days'))->format('Y-m-d');
        $closedLabel = (new \DateTimeImmutable($closedDay))->format('d.m.Y');
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons', [
            'startsOn' => (new \DateTimeImmutable('tomorrow'))->format('Y-m-d'),
            'endsOn' => null,
            'slotDurationMinutes' => 60,
            'openingHours' => array_map(
                static fn (int $weekday): array => ['weekday' => $weekday, 'startTime' => '10:00', 'endTime' => '14:00'],
                range(1, 7),
            ),
            'closures' => [['startsOn' => $closedDay, 'endsOn' => null, 'reason' => 'Revision']],
        ], $headers);
        self::assertResponseStatusCodeSame(201);
        self::assertSame([['startsOn' => $closedDay, 'endsOn' => $closedDay, 'reason' => 'Revision']], $this->responseData()['closures']);

        $this->client->request('GET', '/api/public/v1/sauna/calendar?from='.$closedDay.'&days=1');
        self::assertResponseIsSuccessful();
        $days = $this->responseData()['days'];
        self::assertIsArray($days);
        self::assertIsArray($days[0]);
        self::assertSame([true, 'Revision', []], [$days[0]['closed'], $days[0]['closedReason'], $days[0]['slots']]);

        $guest = ['firstName' => 'Erika', 'lastName' => 'Musterfrau', 'birthDate' => '1990-01-01', 'privacyAccepted' => true];
        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [
            ...$guest, 'date' => $closedDay, 'startTime' => '10:00', 'endTime' => '12:00', 'personCount' => 2, 'participants' => self::names(2),
        ]);
        self::assertResponseStatusCodeSame(422);
        $error = $this->responseData()['error'];
        self::assertIsArray($error);
        self::assertSame('Die Sauna ist am '.$closedLabel.' geschlossen (Revision).', $error['message']);

        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [...$guest, 'individual' => true, 'days' => [
            ['date' => $closedDay, 'startTime' => '18:00', 'endTime' => '20:00', 'personCount' => 2, 'participants' => [
                ['firstName' => 'Erika', 'lastName' => 'Musterfrau'], ['firstName' => 'Max', 'lastName' => 'Muster'],
            ]],
        ]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame([
            'code' => 'saunarequestdayconflictexception',
            'message' => 'Die Sauna ist am '.$closedLabel.' geschlossen (Revision).',
            'details' => ['days' => [0]],
        ], $this->responseData()['error']);
    }

    public function testCalendarReportsRunningOrUpcomingSeasonButNotExpiredOnes(): void
    {
        $this->client->request('GET', '/api/public/v1/sauna/calendar');
        self::assertResponseIsSuccessful();
        self::assertNull($this->responseData()['season']);

        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $this->createSeason('-60 days', '-30 days', $headers);
        $this->client->request('GET', '/api/public/v1/sauna/calendar');
        self::assertNull($this->responseData()['season']);

        $upcomingStart = (new \DateTimeImmutable('+40 days'))->format('Y-m-d');
        $upcomingId = $this->createSeason('+40 days', null, $headers);
        $this->client->request('GET', '/api/public/v1/sauna/calendar');
        self::assertSame(['startsOn' => $upcomingStart, 'endsOn' => null], $this->responseData()['season']);

        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons/'.$upcomingId.'/close', [], $headers);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/public/v1/sauna/calendar');
        self::assertNull($this->responseData()['season']);
    }

    /** @param array<string, string> $headers */
    private function createSeason(string $startsOn, ?string $endsOn, array $headers): string
    {
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-seasons', [
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
            'endTime' => '12:00',
            'personCount' => 2,
            'participants' => self::names(2),
            'firstName' => 'Erika',
            'lastName' => 'Musterfrau',
            'birthDate' => '1990-01-01',
            'privacyAccepted' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * Individuelle Anfrage für zwei Wunschtage innerhalb der Saison, aber außerhalb ihres Zeitrasters:
     * je Tag eine eigene Buchung mit eigenen Personen, beide Tage sind sofort belegt und werden
     * einzeln angenommen. Tage außerhalb der Saison sind nicht anfragbar.
     */
    public function testIndividualRequestReservesEveryDayWithItsOwnParticipants(): void
    {
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->loginAsAdmin()];
        $this->createSeason('tomorrow', '+30 days', $headers);
        $thursday = (new \DateTimeImmutable('+3 days'))->format('Y-m-d');
        $sunday = (new \DateTimeImmutable('+6 days'))->format('Y-m-d');
        $person = static fn (string $firstName, string $lastName): array => ['firstName' => $firstName, 'lastName' => $lastName];
        $request = static fn (array $days, string $lastName = 'Musterfrau'): array => [
            'individual' => true,
            'days' => $days,
            'firstName' => 'Erika',
            'lastName' => $lastName,
            'birthDate' => '1990-01-01',
            'email' => 'erika@example.test',
            'message' => 'Geburtstagsrunde',
            'privacyAccepted' => true,
        ];
        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', $request([
            ['date' => $thursday, 'startTime' => '07:30', 'endTime' => '09:45', 'personCount' => 3,
                'participants' => [$person('Erika', 'Musterfrau'), $person('Max', 'Muster'), $person('Mia', 'Muster')]],
            ['date' => $sunday, 'startTime' => '10:00', 'endTime' => '12:00', 'personCount' => 2,
                'participants' => [$person('Erika', 'Musterfrau'), $person('Tom', 'Muster')]],
        ]));
        self::assertResponseStatusCodeSame(202);

        // Außerhalb der Saison ist keine Anfrage möglich.
        $outsideSeason = (new \DateTimeImmutable('+40 days'))->format('Y-m-d');
        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', $request([
            ['date' => $outsideSeason, 'startTime' => '10:00', 'endTime' => '12:00', 'personCount' => 2,
                'participants' => [$person('Erika', 'Musterfrau'), $person('Max', 'Muster')]],
        ]));
        self::assertResponseStatusCodeSame(422);
        $error = $this->responseData()['error'];
        self::assertIsArray($error);
        self::assertSame('Der '.(new \DateTimeImmutable($outsideSeason))->format('d.m.Y').' liegt außerhalb der Sauna-Saison.', $error['message']);

        // Beide Wunschtage sind reserviert.
        foreach ([[$thursday, '08:00', '10:00'], [$sunday, '11:00', '13:00']] as [$date, $startTime, $endTime]) {
            $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', $request([
                ['date' => $date, 'startTime' => $startTime, 'endTime' => $endTime, 'personCount' => 2,
                    'participants' => [$person('Erika', 'Andere'), $person('Max', 'Andere')]],
            ], 'Andere'));
            self::assertResponseStatusCodeSame(422);
        }

        // Der belegte Wunschtag wird in der Fehlerantwort benannt, damit die Oberfläche ihn markiert.
        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', $request([
            ['date' => (new \DateTimeImmutable('+10 days'))->format('Y-m-d'), 'startTime' => '10:00', 'endTime' => '12:00', 'personCount' => 2,
                'participants' => [$person('Erika', 'Andere'), $person('Max', 'Andere')]],
            ['date' => $sunday, 'startTime' => '11:00', 'endTime' => '13:00', 'personCount' => 2,
                'participants' => [$person('Erika', 'Andere'), $person('Max', 'Andere')]],
        ], 'Andere'));
        self::assertResponseStatusCodeSame(422);
        self::assertSame([
            'code' => 'saunarequestdayconflictexception',
            'message' => 'Wunschtag 2: Der gewählte Zeitraum ist bereits belegt.',
            'details' => ['days' => [1]],
        ], $this->responseData()['error']);

        // Namen passen nicht zur Personenzahl.
        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', $request([
            ['date' => (new \DateTimeImmutable('+10 days'))->format('Y-m-d'), 'startTime' => '10:00', 'endTime' => '12:00', 'personCount' => 3,
                'participants' => [$person('Erika', 'Musterfrau')]],
        ]));
        self::assertResponseStatusCodeSame(422);

        // Eine weitere Anfrage derselben Person (anders geschrieben) wird in der Verwaltung mit der ersten zusammengefasst.
        $laterDay = (new \DateTimeImmutable('+12 days'))->format('Y-m-d');
        $this->client->jsonRequest('POST', '/api/public/v1/sauna/bookings', [...$request([
            ['date' => $laterDay, 'startTime' => '17:00', 'endTime' => '19:00', 'personCount' => 2,
                'participants' => [$person('Erika', 'Musterfrau'), $person('Max', 'Muster')]],
        ], 'MUSTERFRAU'), 'email' => '']);
        self::assertResponseStatusCodeSame(202);

        $this->client->request('GET', '/api/admin/v1/sauna-bookings', [], [], $headers);
        $items = $this->responseData()['items'];
        self::assertIsArray($items);
        self::assertCount(3, $items);
        self::assertIsArray($items[0]);
        self::assertIsArray($items[1]);
        self::assertIsArray($items[2]);
        self::assertTrue($items[0]['individual']);
        self::assertSame($items[0]['requestId'], $items[1]['requestId']);
        self::assertNotSame($items[0]['requestId'], $items[2]['requestId']);
        self::assertSame([$items[0]['requesterKey'], $items[0]['requesterKey']], [$items[1]['requesterKey'], $items[2]['requesterKey']]);
        self::assertSame([2250, 3], [$items[0]['priceCents'], $items[0]['personCount']]);
        self::assertSame([['firstName' => 'Erika', 'lastName' => 'Musterfrau'], ['firstName' => 'Tom', 'lastName' => 'Muster']], $items[1]['participants']);
        self::assertIsString($items[1]['id']);

        self::assertIsString($items[0]['requesterKey']);

        // „Alle annehmen“: alle Tage beider Anfragen gemeinsam, eine Bestätigung mit allen Tagen.
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-bookings/requesters/'.$items[0]['requesterKey'].'/accept', [], $headers);
        self::assertResponseIsSuccessful();
        $accepted = $this->responseData()['items'];
        self::assertIsArray($accepted);
        self::assertSame(['accepted', 'accepted', 'accepted'], array_column($accepted, 'status'));
        self::assertQueuedEmailCount(1);
        $confirmation = self::getMailerMessage();
        self::assertNotNull($confirmation);
        self::assertEmailAddressContains($confirmation, 'To', 'erika@example.test');
        self::assertEmailTextBodyContains($confirmation, (new \DateTimeImmutable($thursday))->format('d.m.Y').', 07:30–09:45 Uhr,');
        self::assertEmailTextBodyContains($confirmation, (new \DateTimeImmutable($sunday))->format('d.m.Y').', 10:00–12:00 Uhr,');
        self::assertEmailTextBodyContains($confirmation, (new \DateTimeImmutable($laterDay))->format('d.m.Y').', 17:00–19:00 Uhr,');

        // Einzeln bleibt jeder Tag bearbeitbar; ohne offene Tage gibt es nichts mehr gemeinsam anzunehmen.
        $this->client->request('POST', '/api/admin/v1/sauna-bookings/'.$items[1]['id'].'/reject', [], [], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame('rejected', $this->responseData()['status']);
        $this->client->request('POST', '/api/admin/v1/sauna-bookings/'.$items[1]['id'].'/accept', [], [], $headers);
        self::assertResponseIsSuccessful();
        self::assertSame('accepted', $this->responseData()['status']);
        $this->client->jsonRequest('POST', '/api/admin/v1/sauna-bookings/requesters/'.$items[0]['requesterKey'].'/accept', [], $headers);
        self::assertResponseStatusCodeSame(422);
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

    /**
     * Namen für eine Kalender-Anfrage mit `$count` Personen (die anfragende Person zuerst).
     *
     * @return list<array{firstName: string, lastName: string}>
     */
    private static function names(int $count): array
    {
        return array_map(
            static fn (int $index): array => $index === 0 ? ['firstName' => 'Erika', 'lastName' => 'Musterfrau'] : ['firstName' => 'Gast', 'lastName' => (string) $index],
            range(0, $count - 1),
        );
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
