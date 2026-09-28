<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mitgliederverwaltung: adds direct_debit_record (Lastschrift-Historie je Zahler: Beitragsjahr, FRST/RCUR, Fälligkeit, Betrag).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('direct_debit_record');
        $table->addColumn('id', 'string', ['length' => 36]);
        $table->addColumn('payer_member_id', 'string', ['length' => 36]);
        $table->addColumn('mandate_reference', 'string', ['length' => 60]);
        $table->addColumn('contribution_year', 'integer');
        $table->addColumn('sequence_type', 'string', ['length' => 4]);
        $table->addColumn('collection_date', 'date_immutable');
        $table->addColumn('amount_cents', 'integer');
        $table->addColumn('message_id', 'string', ['length' => 35]);
        $table->addColumn('exported_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addIndex(['payer_member_id', 'exported_at'], 'idx_direct_debit_record_payer');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('direct_debit_record');
    }
}
