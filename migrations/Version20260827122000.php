<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827122000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Makes plaintext IBAN storage mandatory and removes the obsolete encrypted column.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('membership_application');
        $table->getColumn('iban')->setNotnull(true);
        $table->dropColumn('iban_encrypted');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Die entfernte verschlüsselte IBAN-Spalte lässt sich nicht wiederherstellen.',
        );
    }
}
