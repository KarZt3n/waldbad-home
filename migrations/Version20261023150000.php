<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Data\Common\NativeIdentifierGenerator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Nachtrag zu `Version20261023130000`: die dort vergessene feste Gruppe „Borkwalde“ (3 Personen,
 * donnerstags 17–19 Uhr) – ebenfalls wöchentlich von Saisonbeginn bis Saisonende, ohne Schließtage.
 * Das Buchungsmodell verlangt Vornamen, daher steht dort der Platzhalter „N. N.“.
 */
final class Version20261023150000 extends AbstractMigration
{
    private const string FIRST_DAY = '2026-10-15';
    private const string LAST_DAY = '2027-04-30';
    private const string UNKNOWN_FIRST_NAME = 'N. N.';
    private const string MARKER = 'Feste Gruppe der Saison 2026/27 (per Migration übernommen).';
    private const int WEEKDAY = 4;
    private const string START = '17:00';
    private const string END = '19:00';
    private const string LAST_NAME = 'Borkwalde';
    private const int PERSONS = 3;

    public function getDescription(): string
    {
        return 'Adds the confirmed weekly sauna group Borkwalde (Thursday 17:00-19:00) of season 2026/27.';
    }

    public function up(Schema $schema): void
    {
        $priceCents = $this->connection->fetchOne('SELECT price_cents FROM sauna_terms');
        $priceCents = is_numeric($priceCents) ? (int) $priceCents : 2000;
        $closures = $this->connection->fetchAllAssociative('SELECT starts_on, ends_on FROM sauna_season_closure');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $ids = new NativeIdentifierGenerator();

        $lastDay = new \DateTimeImmutable(self::LAST_DAY);
        for ($day = new \DateTimeImmutable(self::FIRST_DAY); $day <= $lastDay; $day = $day->modify('+1 day')) {
            if ((int) $day->format('N') !== self::WEEKDAY) {
                continue;
            }
            $date = $day->format('Y-m-d');
            foreach ($closures as $closure) {
                if ($date >= $closure['starts_on'] && $date <= $closure['ends_on']) {
                    continue 2;
                }
            }
            $overlapping = $this->connection->fetchOne(
                "SELECT COUNT(*) FROM sauna_booking WHERE booking_date = ? AND start_time < ? AND end_time > ? AND status IN ('open', 'accepted')",
                [$date, self::END, self::START],
            );
            if ((int) $overlapping > 0) {
                $this->write(sprintf('Übersprungen (bereits belegt): %s %s–%s', $date, self::START, self::END));
                continue;
            }

            $bookingId = $ids->generate();
            $this->addSql(
                'INSERT INTO sauna_booking (id, booking_date, start_time, end_time, person_count, price_cents, first_name, last_name, birth_date, email, message, status, member_id, member_number, submitted_at, updated_at, individual, request_id)'
                ." VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, 'accepted', NULL, NULL, ?, ?, ?, NULL)",
                [$bookingId, $date, self::START, self::END, self::PERSONS, $priceCents, self::UNKNOWN_FIRST_NAME, self::LAST_NAME, self::MARKER, $now, $now, 0],
            );
            for ($position = 1; $position <= self::PERSONS; ++$position) {
                $this->addSql(
                    'INSERT INTO sauna_booking_participant (id, booking_id, position, first_name, last_name) VALUES (?, ?, ?, ?, ?)',
                    [$ids->generate(), $bookingId, $position, self::UNKNOWN_FIRST_NAME, self::LAST_NAME],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $bookings = 'SELECT id FROM sauna_booking WHERE message = ? AND last_name = ? AND start_time = ?';
        $this->addSql("DELETE FROM sauna_booking_participant WHERE booking_id IN ({$bookings})", [self::MARKER, self::LAST_NAME, self::START]);
        $this->addSql("DELETE FROM sauna_booking WHERE id IN (SELECT id FROM ({$bookings}) AS borkwalde)", [self::MARKER, self::LAST_NAME, self::START]);
    }
}
