<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds contribution_rate.pending_* (geplante künftige Version des kompletten '
            .'Beitragssatzes, nicht nur des Betrags) and the contribution_rate_settings singleton '
            .'table (gemeinsames „gültig ab" für alle Beitragssätze).';
    }

    public function up(Schema $schema): void
    {
        $rate = $schema->getTable('contribution_rate');
        $rate->addColumn('pending_label', 'string', ['length' => 180, 'notnull' => false]);
        $rate->addColumn('pending_amount_cents', 'integer', ['notnull' => false]);
        $rate->addColumn('pending_period', 'string', ['length' => 20, 'notnull' => false]);
        $rate->addColumn('pending_person_group', 'string', ['length' => 20, 'notnull' => false]);
        $rate->addColumn('pending_min_age', 'integer', ['notnull' => false]);
        $rate->addColumn('pending_max_age', 'integer', ['notnull' => false]);
        $rate->addColumn('pending_valid_from', 'date_immutable', ['notnull' => false]);

        $settings = $schema->createTable('contribution_rate_settings');
        $settings->addColumn('id', 'string', ['length' => 20]);
        $settings->addColumn('valid_from', 'date_immutable', ['notnull' => false]);
        $settings->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('contribution_rate_settings');

        $rate = $schema->getTable('contribution_rate');
        $rate->dropColumn('pending_valid_from');
        $rate->dropColumn('pending_max_age');
        $rate->dropColumn('pending_min_age');
        $rate->dropColumn('pending_person_group');
        $rate->dropColumn('pending_period');
        $rate->dropColumn('pending_amount_cents');
        $rate->dropColumn('pending_label');
    }

    public function postUp(Schema $schema): void
    {
        // Direkt über die Connection statt addSql(): postUp() läuft nach dem Freeze der Migration,
        // addSql() würde dort eine FrozenMigration-Exception auslösen (siehe Version20260909120000).
        $this->connection->executeStatement("INSERT INTO contribution_rate_settings (id, valid_from) VALUES ('default', NULL)");
    }
}
