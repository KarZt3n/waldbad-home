<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drops the SMTP connection columns from email_settings: mail transport now comes '
            .'exclusively from MAILER_DSN/MAILER_FROM_ADDRESS/MAILER_FROM_NAME (deployment '
            .'configuration, plain symfony/mailer) instead of an admin-editable database row, so '
            .'that a missing/incomplete row can no longer silently swallow every outgoing mail.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('email_settings');
        $table->dropColumn('provider');
        $table->dropColumn('host');
        $table->dropColumn('port');
        $table->dropColumn('username');
        $table->dropColumn('password');
        $table->dropColumn('from_address');
        $table->dropColumn('from_name');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('email_settings');
        $table->addColumn('provider', 'string', ['length' => 20, 'notnull' => false]);
        $table->addColumn('host', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('port', 'integer', ['notnull' => false]);
        $table->addColumn('username', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('password', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('from_address', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('from_name', 'string', ['length' => 180, 'notnull' => false]);
    }
}
