<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vermietung/Sauna: adds sauna_booking.individual (individuelle Anfrage mit freier Wunschzeit '
            .'außerhalb des Saison-Zeitrasters).';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('sauna_booking')->addColumn('individual', 'boolean', ['default' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('sauna_booking')->dropColumn('individual');
    }
}
