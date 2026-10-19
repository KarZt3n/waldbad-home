<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Makes sauna_booking.birth_date nullable (Sauna-Anfragen nur noch mit Namen).';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('sauna_booking')->getColumn('birth_date')->setNotnull(false);
    }

    public function down(Schema $schema): void
    {
        // Schlägt fehl, solange Anfragen ohne Geburtsdatum gespeichert sind.
        $schema->getTable('sauna_booking')->getColumn('birth_date')->setNotnull(true);
    }
}
