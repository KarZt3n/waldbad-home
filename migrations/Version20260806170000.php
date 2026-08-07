<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds multiple editable participation intervals for event volunteers.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('event_help_interval');
        $table->addColumn('id', 'string', ['length' => 36]);
        $table->addColumn('request_id', 'string', ['length' => 36]);
        $table->addColumn('position', 'integer');
        $table->addColumn('from_time', 'string', ['length' => 5]);
        $table->addColumn('to_time', 'string', ['length' => 5]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['request_id', 'position'], 'idx_event_help_interval_request_position');
        $table->addForeignKeyConstraint('event_help_request', ['request_id'], ['id'], ['onDelete' => 'CASCADE']);

    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('event_help_interval');
    }
}
