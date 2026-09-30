<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\AbortMigration;

/**
 * Die neue Tabelle erhält ausdrücklich dieselbe Sortierung wie `sauna_booking`: Ohne Angabe legt
 * MariaDB sie mit der Standard-Sortierung des Servers an (je nach Version z. B.
 * `utf8mb4_uca1400_ai_ci` statt `utf8mb4_unicode_520_ci`), und ein Fremdschlüssel zwischen
 * Spalten unterschiedlicher Sortierung scheitert mit errno 150 („Foreign key constraint is
 * incorrectly formed“), siehe auch Version20260928180000.
 *
 * Jeder Schritt wird nur ausgeführt, wenn er noch fehlt: MariaDB schreibt DDL sofort fest, ein
 * abgebrochener Lauf (Tabelle angelegt, Fremdschlüssel gescheitert) lässt sich so erneut ausführen.
 */
final class Version20260930100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vermietung/Sauna: adds sauna_booking.request_id (gemeinsame Kennung der je Wunschtag angelegten '
            .'Buchungen einer individuellen Anfrage) and sauna_booking_participant (Namen der Personen).';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $collation = $this->connection->fetchOne(
            "SELECT table_collation FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sauna_booking'",
        );
        if (!is_string($collation) || preg_match('/^utf8mb4_[a-z0-9_]+$/', $collation) !== 1) {
            throw new AbortMigration('Die Sortierung der Tabelle sauna_booking konnte nicht ermittelt werden.');
        }

        $booking = $schema->getTable('sauna_booking');
        if (!$booking->hasColumn('request_id')) {
            $this->addSql('ALTER TABLE sauna_booking ADD request_id VARCHAR(36) DEFAULT NULL');
        }
        if (!$booking->hasIndex('idx_sauna_booking_request')) {
            $this->addSql('CREATE INDEX idx_sauna_booking_request ON sauna_booking (request_id)');
        }

        if (!$schema->hasTable('sauna_booking_participant')) {
            $this->addSql(sprintf(
                'CREATE TABLE sauna_booking_participant (id VARCHAR(36) NOT NULL, booking_id VARCHAR(36) NOT NULL, position SMALLINT NOT NULL, '
                .'first_name VARCHAR(120) NOT NULL, last_name VARCHAR(120) NOT NULL, '
                .'INDEX idx_sauna_booking_participant_booking_position (booking_id, position), INDEX IDX_BD56D74A3301C60 (booking_id), '
                .'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE %s ENGINE = InnoDB',
                $collation,
            ));
        } else {
            $participant = $schema->getTable('sauna_booking_participant');
            $this->addSql(sprintf('ALTER TABLE sauna_booking_participant CONVERT TO CHARACTER SET utf8mb4 COLLATE %s', $collation));
            if (!$participant->hasIndex('IDX_BD56D74A3301C60')) {
                $this->addSql('CREATE INDEX IDX_BD56D74A3301C60 ON sauna_booking_participant (booking_id)');
            }
            if ($participant->getForeignKeys() !== []) {
                return;
            }
        }
        $this->addSql('ALTER TABLE sauna_booking_participant ADD CONSTRAINT FK_BD56D74A3301C60 FOREIGN KEY (booking_id) REFERENCES sauna_booking (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sauna_booking_participant');
        $this->addSql('DROP INDEX idx_sauna_booking_request ON sauna_booking');
        $this->addSql('ALTER TABLE sauna_booking DROP request_id');
    }
}
