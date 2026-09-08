<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replaces the single age_threshold column with the uniform min_age/max_age range used by every contribution rate, and allows deleting any contribution rate (including the fixed categories).';
    }

    public function up(Schema $schema): void
    {
        // "bis unter X Jahre" (age_threshold, exklusiv) wird zur einschließenden Altersspanne
        // (max_age = age_threshold - 1), damit dieselbe Bedeutung wie zuvor erhalten bleibt.
        $this->connection->executeStatement(
            'UPDATE contribution_rate SET max_age = age_threshold - 1 WHERE age_threshold IS NOT NULL AND max_age IS NULL',
        );

        // Die verbleibenden Grundkategorien hatten bisher keine eigene Altersspanne (sie ergaben
        // sich implizit als "sonst"-Zweig im Code). Da die Berechnung ab jetzt ausschließlich über
        // die hinterlegte Altersspanne entscheidet, bekommen sie hier explizit dieselben
        // Altersgrenzen, die zuvor implizit galten.
        $this->connection->executeStatement(
            "UPDATE contribution_rate SET min_age = 21 WHERE category = 'individual_senior' AND min_age IS NULL AND max_age IS NULL",
        );
        $this->connection->executeStatement(
            "UPDATE contribution_rate SET min_age = 4, max_age = 20 WHERE category = 'family_child_paying' AND min_age IS NULL AND max_age IS NULL",
        );

        $schema->getTable('contribution_rate')->dropColumn('age_threshold');
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('contribution_rate')->addColumn('age_threshold', 'integer', ['notnull' => false]);
    }

    /**
     * Erst nachdem die Spalte durch down() tatsächlich wieder angelegt wurde (postDown() läuft
     * danach, analog zu postUp()), kann sie befüllt werden.
     */
    public function postDown(Schema $schema): void
    {
        $this->connection->executeStatement(
            "UPDATE contribution_rate SET age_threshold = max_age + 1 WHERE category IN ('individual_junior', 'family_child_exempt') AND max_age IS NOT NULL",
        );
        $this->connection->executeStatement(
            "UPDATE contribution_rate SET max_age = NULL WHERE category IN ('individual_junior', 'family_child_exempt')",
        );
        $this->connection->executeStatement(
            "UPDATE contribution_rate SET min_age = NULL WHERE category = 'individual_senior'",
        );
        $this->connection->executeStatement(
            "UPDATE contribution_rate SET min_age = NULL, max_age = NULL WHERE category = 'family_child_paying'",
        );
    }
}
