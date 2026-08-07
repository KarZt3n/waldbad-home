<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806171000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrates existing volunteer participation times into participation intervals.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO event_help_interval (id, request_id, position, from_time, to_time)
            SELECT CONCAT('legacy-', SUBSTRING(SHA2(id, 256), 1, 29)), id, 0, participation_from_time, participation_to_time
            FROM event_help_request
            WHERE participation_from_time IS NOT NULL AND participation_to_time IS NOT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM event_help_interval WHERE id LIKE 'legacy-%'");
    }
}
