<?php

namespace App\Tests\Integration\Data\Rental\Sauna;

use App\Data\Rental\Sauna\Booking\Processor\DoctrineSaunaBookingProcessor;
use App\Data\Rental\Sauna\Booking\Provider\DoctrineSaunaBookingProvider;
use App\Data\Rental\Sauna\Season\Processor\DoctrineSaunaSeasonProcessor;
use App\Data\Rental\Sauna\Season\Provider\DoctrineSaunaSeasonProvider;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingParticipant;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Season\Model\SaunaClosure;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\Model\Weekday;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineSaunaPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('Der EntityManager ist im Testcontainer nicht verfügbar.');
        }
        $this->entityManager = $entityManager;
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testSeasonRoundTripReplacesOpeningHoursAndClosuresOnUpdate(): void
    {
        $processor = $this->service(DoctrineSaunaSeasonProcessor::class);
        $provider = $this->service(DoctrineSaunaSeasonProvider::class);
        $now = new \DateTimeImmutable('2026-09-25T10:00:00');
        $season = new SaunaSeason(
            id: 'season-1',
            startsOn: new \DateTimeImmutable('2026-10-01'),
            endsOn: null,
            slotDurationMinutes: 90,
            openingHours: [
                new SaunaOpeningHours(Weekday::Monday, '16:00', '19:00'),
                new SaunaOpeningHours(Weekday::Saturday, '10:00', '13:00'),
            ],
            createdAt: $now,
            updatedAt: $now,
            closures: [new SaunaClosure(new \DateTimeImmutable('2026-10-12'), new \DateTimeImmutable('2026-10-12'), 'Revision')],
        );
        $processor->save($season);
        $processor->save($season->revise(
            startsOn: $season->startsOn,
            endsOn: new \DateTimeImmutable('2027-03-31'),
            slotDurationMinutes: 60,
            openingHours: [new SaunaOpeningHours(Weekday::Sunday, '14:00', '18:00')],
            closures: [
                new SaunaClosure(new \DateTimeImmutable('2026-12-24'), new \DateTimeImmutable('2026-12-26'), 'Weihnachten'),
                new SaunaClosure(new \DateTimeImmutable('2027-01-01'), new \DateTimeImmutable('2027-01-01')),
            ],
            updatedAt: $now->modify('+1 hour'),
        ));
        $this->entityManager->clear();

        $loaded = $provider->find('season-1');

        self::assertNotNull($loaded);
        self::assertSame('2027-03-31', $loaded->endsOn?->format('Y-m-d'));
        self::assertSame(60, $loaded->slotDurationMinutes);
        self::assertCount(1, $loaded->openingHours);
        self::assertSame(Weekday::Sunday, $loaded->openingHours[0]->weekday);
        self::assertSame('14:00', $loaded->openingHours[0]->startTime);
        self::assertSame(
            [['2026-12-24', '2026-12-26', 'Weihnachten'], ['2027-01-01', '2027-01-01', '']],
            array_map(static fn (SaunaClosure $closure): array => [
                $closure->startsOn->format('Y-m-d'),
                $closure->endsOn->format('Y-m-d'),
                $closure->reason,
            ], $loaded->closures),
        );
    }

    public function testBookingsAreFoundByInclusiveDateRangeAndStatusIsUpdated(): void
    {
        $processor = $this->service(DoctrineSaunaBookingProcessor::class);
        $provider = $this->service(DoctrineSaunaBookingProvider::class);
        $processor->save($this->booking('before', '2026-10-04'));
        $processor->save($this->booking('first-day', '2026-10-05'));
        $processor->save($this->booking('last-day', '2026-10-11'));
        $processor->save($this->booking('after', '2026-10-12'));
        $firstDay = $provider->find('first-day');
        self::assertNotNull($firstDay);
        $processor->save($firstDay->accept(new \DateTimeImmutable('2026-09-26T09:00:00')));
        $this->entityManager->clear();

        $found = $provider->findBetween(new \DateTimeImmutable('2026-10-05'), new \DateTimeImmutable('2026-10-11'));

        self::assertSame(['first-day', 'last-day'], array_map(static fn (SaunaBooking $booking): string => $booking->id, $found));
        self::assertSame(SaunaBookingStatus::Accepted, $found[0]->status);
        self::assertSame('M-100', $found[0]->memberNumber);
    }

    public function testSaveAllStoresEveryDayOfARequestWithItsParticipants(): void
    {
        $processor = $this->service(DoctrineSaunaBookingProcessor::class);
        $provider = $this->service(DoctrineSaunaBookingProvider::class);
        $processor->saveAll([
            $this->booking('thursday', '2026-10-08', 'request-1', ['Erika Musterfrau', 'Max Muster']),
            $this->booking('sunday', '2026-10-11', 'request-1', ['Erika Musterfrau', 'Mia Muster']),
        ]);
        $this->entityManager->clear();

        $found = $provider->findBetween(new \DateTimeImmutable('2026-10-08'), new \DateTimeImmutable('2026-10-11'));

        self::assertSame(['thursday', 'sunday'], array_map(static fn (SaunaBooking $booking): string => $booking->id, $found));
        self::assertSame(['request-1', 'request-1'], array_map(static fn (SaunaBooking $booking): ?string => $booking->requestId, $found));
        self::assertSame(
            ['Erika Musterfrau', 'Mia Muster'],
            array_map(static fn (SaunaBookingParticipant $participant): string => $participant->firstName.' '.$participant->lastName, $found[1]->participants),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function service(string $class): object
    {
        $service = self::getContainer()->get($class);
        if (!$service instanceof $class) {
            throw new \LogicException(sprintf('%s ist im Testcontainer nicht verfügbar.', $class));
        }

        return $service;
    }

    /**
     * @param list<string> $participantNames
     */
    private function booking(string $id, string $date, ?string $requestId = null, array $participantNames = []): SaunaBooking
    {
        $submittedAt = new \DateTimeImmutable('2026-09-25T10:00:00');

        return new SaunaBooking(
            id: $id,
            date: new \DateTimeImmutable($date),
            startTime: '18:00',
            endTime: '19:00',
            personCount: $participantNames === [] ? 4 : count($participantNames),
            priceCents: 2000,
            firstName: 'Erika',
            lastName: 'Musterfrau',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            email: null,
            message: '',
            status: SaunaBookingStatus::Open,
            memberId: 'member-1',
            memberNumber: 'M-100',
            submittedAt: $submittedAt,
            updatedAt: $submittedAt,
            individual: $requestId !== null,
            requestId: $requestId,
            participants: array_map(static function (string $name) use ($id): SaunaBookingParticipant {
                [$firstName, $lastName] = explode(' ', $name);

                return new SaunaBookingParticipant($id.'-'.$firstName, $firstName, $lastName);
            }, $participantNames),
        );
    }
}
