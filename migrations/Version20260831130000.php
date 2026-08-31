<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds an "always included" flag to the activity catalog so an activity can be pre-selected automatically when a new event is created.';
    }

    public function up(Schema $schema): void
    {
        // Added via raw SQL (with a temporary default to backfill existing rows, then dropped
        // again) so the resulting column matches the entity mapping exactly: NOT NULL, no default.
        $this->addSql('ALTER TABLE event_activity ADD always_included TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE event_activity ALTER always_included DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('event_activity')->dropColumn('always_included');
    }
}
