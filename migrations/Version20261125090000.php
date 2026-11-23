<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds member.contribution_year (Beitragsjahr, für das der Beitrag zuletzt berechnet wurde) and clears the next booking of members paying through another member.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member ADD contribution_year INT DEFAULT NULL');
        // Bis hierher wurden alle Beiträge für 2026 berechnet. Ein Beitragsexport für 2027 verlangt
        // danach erst „Beiträge neu berechnen“ für 2027.
        $this->addSql('UPDATE member SET contribution_year = 2026 WHERE contribution_category IS NOT NULL');
        // Nur Selbstzahler haben eine eigene Buchung (siehe `MemberModelFactory`); bei Mitzahlern stammen
        // die Werte aus der Sage-Übernahme und werden nie ausgewertet.
        $this->addSql("UPDATE member SET next_booking_month = NULL, next_booking_year = NULL WHERE payer_type = 'other_member'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member DROP contribution_year');
    }
}
