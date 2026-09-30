<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vermietung/Sauna: adds sauna_season_closure (Schließzeiten einer Saison, an denen die Sauna trotz Wochenplan geschlossen ist).';
    }

    public function up(Schema $schema): void
    {
        $closure = $schema->createTable('sauna_season_closure');
        $closure->addColumn('id', 'string', ['length' => 36]);
        $closure->addColumn('season_id', 'string', ['length' => 36]);
        $closure->addColumn('position', 'integer');
        $closure->addColumn('starts_on', 'date_immutable');
        $closure->addColumn('ends_on', 'date_immutable');
        $closure->addColumn('reason', 'string', ['length' => 120]);
        $closure->setPrimaryKey(['id']);
        $closure->addIndex(['season_id', 'position'], 'idx_sauna_season_closure_season_position');
        $closure->addForeignKeyConstraint('sauna_season', ['season_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('sauna_season_closure');
    }
}
