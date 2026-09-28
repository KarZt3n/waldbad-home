<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mitgliederverwaltung: adds direct_debit_creditor (SEPA-Gläubigerdaten des Vereins für den Lastschrift-Export).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('direct_debit_creditor');
        $table->addColumn('id', 'string', ['length' => 20]);
        $table->addColumn('name', 'string', ['length' => 70, 'notnull' => false]);
        $table->addColumn('creditor_id', 'string', ['length' => 35, 'notnull' => false]);
        $table->addColumn('iban', 'string', ['length' => 34, 'notnull' => false]);
        $table->addColumn('bic', 'string', ['length' => 11, 'notnull' => false]);
        $table->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('direct_debit_creditor');
    }
}
