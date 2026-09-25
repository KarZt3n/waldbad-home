<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vermietung/Sauna: drops sauna_season.name — eine Saison wird über ihren Zeitraum identifiziert, '
            .'eine eigene Bezeichnung wird nicht benötigt.';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('sauna_season')->dropColumn('name');
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('sauna_season')->addColumn('name', 'string', ['length' => 120, 'default' => 'Saison']);
    }
}
