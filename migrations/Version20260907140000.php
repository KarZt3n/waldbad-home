<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allows custom (deletable) contribution rates without a fixed category and adds configurable age thresholds for the automatic contribution calculation.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('contribution_rate');
        $table->getColumn('category')->setNotnull(false);
        $table->addColumn('age_threshold', 'integer', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('contribution_rate');
        $table->dropColumn('age_threshold');
        $table->getColumn('category')->setNotnull(true);
    }

    /**
     * Startwerte der Altersgrenzen für die beiden davon abhängigen Beitragssätze, entsprechend der
     * bisherigen (fest im Code stehenden) Regeln: „Einzelperson bis 21 Jahre“ und „Kinder unter 4
     * Jahren beitragsfrei“. Ab jetzt im Admin unter „Beitragssätze“ anpassbar.
     */
    public function postUp(Schema $schema): void
    {
        $this->connection->executeStatement(
            "UPDATE contribution_rate SET age_threshold = 21 WHERE category = 'individual_junior'",
        );
        $this->connection->executeStatement(
            "UPDATE contribution_rate SET age_threshold = 4 WHERE category = 'family_child_exempt'",
        );
    }
}
