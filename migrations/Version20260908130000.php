<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds mandate_valid_from/mandate_valid_until to member (MANDATABDATUM/MANDATBISDATUM '
            .'from the Sage GS export) so the SEPA mandate validity period can be tracked.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('member');
        $table->addColumn('mandate_valid_from', 'date_immutable', ['notnull' => false]);
        $table->addColumn('mandate_valid_until', 'date_immutable', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('member');
        $table->dropColumn('mandate_valid_until');
        $table->dropColumn('mandate_valid_from');
    }
}
