<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Data\Common\NativeIdentifierGenerator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Legt die festen Saunagruppen der Saison 2026/27 aus `Version20261023130000` und `Version20261023150000`
 * neu an: Die dort übernommenen Buchungen werden entfernt und ohne die Feiertage 24.12., 25.12., 31.12.
 * und 01.01. (Sauna geschlossen) sowie ohne Schließtage erneut erzeugt. Die Schließtage waren beim ersten
 * Lauf nicht überall hinterlegt. Die Test-Anfragen-Bereinigung der ersten Migration wird nicht wiederholt.
 */
final class Version20261023170000 extends AbstractMigration
{
    private const string FIRST_DAY = '2026-10-15';
    private const string LAST_DAY = '2027-04-30';
    private const string UNKNOWN_FIRST_NAME = 'N. N.';
    private const string MARKER = 'Feste Gruppe der Saison 2026/27 (per Migration übernommen).';
    private const array CLOSED_DAYS = ['2026-12-24', '2026-12-25', '2026-12-31', '2027-01-01'];

    /** @var list<array{weekday: int, start: string, end: string, persons: list<array{string, int}>}> */
    private const array GROUPS = [
        ['weekday' => 4, 'start' => '17:00', 'end' => '19:00', 'persons' => [['Borkwalde', 3]]],
        ['weekday' => 4, 'start' => '19:00', 'end' => '21:00', 'persons' => [['Spiegel', 2], ['Meixner', 2], ['Kroschwitz', 1]]],
        ['weekday' => 5, 'start' => '15:00', 'end' => '17:00', 'persons' => [['Schüler', 1], ['Walke', 1], ['Varadi', 3]]],
        ['weekday' => 5, 'start' => '17:00', 'end' => '19:00', 'persons' => [['Schäfer/Greiner', 4], ['Penzel', 2]]],
        ['weekday' => 7, 'start' => '17:00', 'end' => '19:00', 'persons' => [['Barucki & Co.', 6]]],
    ];

    public function getDescription(): string
    {
        return 'Recreates the confirmed weekly sauna groups of season 2026/27 without 24.12., 25.12., 31.12. and 01.01.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM sauna_booking_participant WHERE booking_id IN (SELECT id FROM sauna_booking WHERE message = ?)', [self::MARKER]);
        $this->addSql('DELETE FROM sauna_booking WHERE message = ?', [self::MARKER]);

        $priceCents = $this->connection->fetchOne('SELECT price_cents FROM sauna_terms');
        $priceCents = is_numeric($priceCents) ? (int) $priceCents : 2000;
        $closures = $this->connection->fetchAllAssociative('SELECT starts_on, ends_on FROM sauna_season_closure');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $ids = new NativeIdentifierGenerator();

        $lastDay = new \DateTimeImmutable(self::LAST_DAY);
        for ($day = new \DateTimeImmutable(self::FIRST_DAY); $day <= $lastDay; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            if (in_array($date, self::CLOSED_DAYS, true)) {
                continue;
            }
            foreach ($closures as $closure) {
                if ($date >= $closure['starts_on'] && $date <= $closure['ends_on']) {
                    continue 2;
                }
            }
            foreach (self::GROUPS as $group) {
                if ((int) $day->format('N') !== $group['weekday']) {
                    continue;
                }
                // Die eigenen, oben gelöschten Buchungen zählen nicht; echte Anfragen bleiben unangetastet.
                $overlapping = $this->connection->fetchOne(
                    "SELECT COUNT(*) FROM sauna_booking WHERE booking_date = ? AND start_time < ? AND end_time > ? AND status IN ('open', 'accepted') AND (message IS NULL OR message <> ?)",
                    [$date, $group['end'], $group['start'], self::MARKER],
                );
                if (is_numeric($overlapping) && (int) $overlapping > 0) {
                    $this->write(sprintf('Übersprungen (bereits belegt): %s %s–%s', $date, $group['start'], $group['end']));
                    continue;
                }

                $bookingId = $ids->generate();
                $personCount = array_sum(array_column($group['persons'], 1));
                $this->addSql(
                    'INSERT INTO sauna_booking (id, booking_date, start_time, end_time, person_count, price_cents, first_name, last_name, birth_date, email, message, status, member_id, member_number, submitted_at, updated_at, individual, request_id)'
                    ." VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, 'accepted', NULL, NULL, ?, ?, ?, NULL)",
                    [$bookingId, $date, $group['start'], $group['end'], $personCount, $priceCents, self::UNKNOWN_FIRST_NAME, $group['persons'][0][0], self::MARKER, $now, $now, 0],
                );
                $position = 0;
                foreach ($group['persons'] as [$lastName, $count]) {
                    for ($i = 0; $i < $count; ++$i) {
                        $this->addSql(
                            'INSERT INTO sauna_booking_participant (id, booking_id, position, first_name, last_name) VALUES (?, ?, ?, ?, ?)',
                            [$ids->generate(), $bookingId, ++$position, self::UNKNOWN_FIRST_NAME, $lastName],
                        );
                    }
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM sauna_booking_participant WHERE booking_id IN (SELECT id FROM sauna_booking WHERE message = ?)', [self::MARKER]);
        $this->addSql('DELETE FROM sauna_booking WHERE message = ?', [self::MARKER]);
    }
}
