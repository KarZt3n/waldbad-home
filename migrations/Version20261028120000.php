<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Den Status „In Bearbeitung“ für Kontaktanfragen gibt es nicht mehr; solche Anfragen sind wieder offen. */
final class Version20261028120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Moves contact requests in progress back to new after removing the in_progress status.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE contact_request SET status = 'new' WHERE status = 'in_progress'");
    }

    public function down(Schema $schema): void
    {
        // Welche Anfragen zuvor in Bearbeitung waren, ist nicht mehr bekannt.
    }
}
