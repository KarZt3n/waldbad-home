<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds sauna_terms.lead_days (Vorlauf in Tagen für Sauna-Anfragen).';
    }

    public function up(Schema $schema): void
    {
        // Default nur für einen bereits gespeicherten Konditionen-Datensatz (Ausgangswert `SaunaTerms::DEFAULT_LEAD_DAYS`).
        $schema->getTable('sauna_terms')->addColumn('lead_days', 'smallint', ['default' => 2]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('sauna_terms')->dropColumn('lead_days');
    }
}
