<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds salutation to membership_applicant so the public application form can '
            .'capture it per person instead of defaulting every released member to "Divers".';
    }

    public function up(Schema $schema): void
    {
        $applicant = $schema->getTable('membership_applicant');
        // Default "diverse" für bereits eingegangene Anträge, die noch keine Anrede erfasst haben;
        // neue Einreichungen über das öffentliche Formular liefern immer einen expliziten Wert.
        $applicant->addColumn('salutation', 'string', ['length' => 20, 'default' => 'diverse']);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('membership_applicant')->dropColumn('salutation');
    }
}
