<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\AbortMigration;

/**
 * Wie Version20260930100000: Die neue Tabelle erhält dieselbe Sortierung wie `sauna_season`, sonst
 * scheitert der Fremdschlüssel je nach Standard-Sortierung des Servers mit errno 150; die Schritte
 * laufen nur, wenn sie noch fehlen.
 */
final class Version20260930140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vermietung/Sauna: adds sauna_season_closure (Schließzeiten einer Saison, an denen die Sauna trotz Wochenplan geschlossen ist).';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $collation = $this->connection->fetchOne(
            "SELECT table_collation FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sauna_season'",
        );
        if (!is_string($collation) || preg_match('/^utf8mb4_[a-z0-9_]+$/', $collation) !== 1) {
            throw new AbortMigration('Die Sortierung der Tabelle sauna_season konnte nicht ermittelt werden.');
        }

        if (!$schema->hasTable('sauna_season_closure')) {
            $this->addSql(sprintf(
                'CREATE TABLE sauna_season_closure (id VARCHAR(36) NOT NULL, season_id VARCHAR(36) NOT NULL, position INT NOT NULL, '
                .'starts_on DATE NOT NULL, ends_on DATE NOT NULL, reason VARCHAR(120) NOT NULL, '
                .'INDEX idx_sauna_season_closure_season_position (season_id, position), INDEX IDX_3D7EABDD4EC001D1 (season_id), '
                .'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE %s ENGINE = InnoDB',
                $collation,
            ));
        } else {
            $closure = $schema->getTable('sauna_season_closure');
            $this->addSql(sprintf('ALTER TABLE sauna_season_closure CONVERT TO CHARACTER SET utf8mb4 COLLATE %s', $collation));
            if (!$closure->hasIndex('IDX_3D7EABDD4EC001D1')) {
                $this->addSql('CREATE INDEX IDX_3D7EABDD4EC001D1 ON sauna_season_closure (season_id)');
            }
            if ($closure->getForeignKeys() !== []) {
                return;
            }
        }
        $this->addSql('ALTER TABLE sauna_season_closure ADD CONSTRAINT FK_3D7EABDD4EC001D1 FOREIGN KEY (season_id) REFERENCES sauna_season (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sauna_season_closure');
    }
}
