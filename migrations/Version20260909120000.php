<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the pin_settings singleton table for the new PIN protection settings module (Admin/Super-Admin only).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('pin_settings');
        $table->addColumn('id', 'string', ['length' => 20]);
        $table->addColumn('pin_hash', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('protected_actions', 'json');
        $table->addColumn('updated_at', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('pin_settings');
    }

    public function postUp(Schema $schema): void
    {
        // Direkt über die Connection statt addSql(): postUp() läuft nach dem Freeze der Migration,
        // addSql() würde dort eine FrozenMigration-Exception auslösen.
        $this->connection->executeStatement(
            "INSERT INTO pin_settings (id, pin_hash, protected_actions, updated_at) VALUES ('default', NULL, '[]', NULL)",
        );
    }
}
