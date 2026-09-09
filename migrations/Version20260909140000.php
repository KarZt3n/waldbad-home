<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the email_settings singleton table for the new E-Mail-Einstellungen tab '
            .'(SMTP credentials plus per-NotificationEvent recipients).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('email_settings');
        $table->addColumn('id', 'string', ['length' => 20]);
        $table->addColumn('provider', 'string', ['length' => 20, 'notnull' => false]);
        $table->addColumn('host', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('port', 'integer', ['notnull' => false]);
        $table->addColumn('username', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('password', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('from_address', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('from_name', 'string', ['length' => 180, 'notnull' => false]);
        $table->addColumn('notification_recipients', 'json', ['notnull' => false]);
        $table->addColumn('updated_at', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('email_settings');
    }

    public function postUp(Schema $schema): void
    {
        // Direkt über die Connection statt addSql(): postUp() läuft nach dem Freeze der Migration,
        // addSql() würde dort eine FrozenMigration-Exception auslösen.
        $this->connection->executeStatement(
            "INSERT INTO email_settings (id, provider, host, port, username, password, from_address, from_name, notification_recipients, updated_at) "
            ."VALUES ('default', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '[]', NULL)",
        );
    }
}
