<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Veranstaltungshelfer: Hilfezeitraum ohne Bis-Uhrzeit für anwesende, noch nicht ausgetragene Personen (event_help_interval.to_time nullable).';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('event_help_interval')->getColumn('to_time')->setNotnull(false);
    }

    public function down(Schema $schema): void
    {
        // Offene Zeiträume müssen vor dem Zurückrollen ausgetragen oder entfernt werden.
        $schema->getTable('event_help_interval')->getColumn('to_time')->setNotnull(true);
    }
}
