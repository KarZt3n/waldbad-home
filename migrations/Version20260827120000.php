<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds plaintext storage for membership application IBANs.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('membership_application');
        $table->addColumn('iban', 'text', ['notnull' => false]);
        $table->getColumn('iban_encrypted')->setNotnull(false);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Neue Klartext-IBANs besitzen keinen verschlüsselten Rückfallwert.',
        );
    }
}
