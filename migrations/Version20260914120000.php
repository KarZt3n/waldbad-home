<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drops member.active — der Aktiv-Status wird nicht mehr eigenständig gespeichert, '
            .'sondern ausschließlich aus joined_at/left_at ermittelt (siehe Member::isActive()).';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('member')->dropColumn('active');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('member');
        $table->addColumn('active', 'boolean', ['default' => true]);
    }

    public function postDown(Schema $schema): void
    {
        // Direkt über die Connection statt addSql(): postDown() läuft nach dem Freeze der
        // Migration, addSql() würde dort eine FrozenMigration-Exception auslösen. Bestehende
        // Zeilen bekommen denselben Wert, den Member::isActive() heute liefern würde.
        $this->connection->executeStatement(
            'UPDATE member SET active = (left_at IS NULL OR left_at > CURRENT_DATE)',
        );
    }
}
