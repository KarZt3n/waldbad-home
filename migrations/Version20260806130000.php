<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds volunteer registrations for CMS events.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('event_help_request');
        $table->addColumn('id', 'string', ['length' => 36]);
        $table->addColumn('event_identifier', 'string', ['length' => 80]);
        $table->addColumn('event_title', 'string', ['length' => 180]);
        $table->addColumn('event_date', 'string', ['length' => 10]);
        $table->addColumn('event_time', 'string', ['length' => 5]);
        $table->addColumn('first_name', 'string', ['length' => 120]);
        $table->addColumn('last_name', 'string', ['length' => 120]);
        $table->addColumn('message', 'text');
        $table->addColumn('status', 'string', ['length' => 20]);
        $table->addColumn('submitted_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addIndex(['event_identifier', 'status'], 'idx_event_help_event_status');
        $table->addIndex(['submitted_at'], 'idx_event_help_submitted');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('event_help_request');
    }
}
