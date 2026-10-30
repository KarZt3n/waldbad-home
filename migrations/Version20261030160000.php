<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Das Fotoarchiv zeigt das Datum eines Eintrags inzwischen unter dem Titel an; die aus der alten
 * Website übernommenen Titel enden aber meist selbst auf „am 25.07.2026“ bzw. „am 14. Juni 2003“.
 * Dieser Zusatz wird nur entfernt, wenn er genau dem gepflegten Datum entspricht — Zeiträume („am
 * 10./11.09.2022“) und Titel mit weiterem Text dahinter bleiben unverändert, damit keine Angabe verloren geht.
 */
final class Version20261030160000 extends AbstractMigration
{
    private const array MONTHS = [
        'januar' => 1, 'februar' => 2, 'märz' => 3, 'april' => 4, 'mai' => 5, 'juni' => 6,
        'juli' => 7, 'august' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'dezember' => 12,
    ];

    public function getDescription(): string
    {
        return 'Removes the trailing „am <Datum>“ from photo album titles when it equals the album date.';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->connection->fetchAllAssociative('SELECT id, title, album_date FROM photo_album') as $row) {
            $id = $row['id'] ?? null;
            $title = $row['title'] ?? null;
            $albumDate = $row['album_date'] ?? null;
            if (!is_string($id) || !is_string($title) || !is_string($albumDate)) {
                throw new \RuntimeException('Die Fotoarchiv-Einträge konnten nicht vollständig gelesen werden.');
            }
            $shortened = self::withoutTrailingDate($title, substr($albumDate, 0, 10));
            if ($shortened !== null) {
                $this->addSql('UPDATE photo_album SET title = :title WHERE id = :id', ['title' => $shortened, 'id' => $id]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Die ursprüngliche Schreibweise des Datums im Titel ist nicht mehr bekannt.');
    }

    private static function withoutTrailingDate(string $title, string $albumDate): ?string
    {
        if (preg_match('/^(.*\S)\s+am\s+(\d{1,2})\.\s*(?:(\d{1,2})\.|([[:alpha:]äÄ]+)\s+)(\d{4})\s*$/u', $title, $match) !== 1) {
            return null;
        }
        $month = $match[3] !== '' ? (int) $match[3] : (self::MONTHS[mb_strtolower($match[4])] ?? 0);
        $date = sprintf('%04d-%02d-%02d', (int) $match[5], $month, (int) $match[2]);

        return $date === $albumDate ? $match[1] : null;
    }
}
